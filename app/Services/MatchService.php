<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\Team;
use App\Models\VolleyballMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchService
{
    public function __construct(private TeamBalanceService $balance) {}

    public function start(Meeting $meeting, Team $home, Team $away, bool $allowIncomplete = false): VolleyballMatch
    {
        return DB::transaction(function () use ($meeting, $home, $away, $allowIncomplete): VolleyballMatch {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);

            if ($meeting->status !== 'active' || $meeting->matches()->where('status', 'playing')->exists()) {
                throw ValidationException::withMessages(['score' => 'Não é possível iniciar outra partida neste encontro.']);
            }

            if ($home->id === $away->id || $home->meeting_id !== $meeting->id || $away->meeting_id !== $meeting->id) {
                throw ValidationException::withMessages(['score' => 'Os times escolhidos não pertencem a esta partida.']);
            }

            $homeCount = $home->participants()->where('status', 'present')->count();
            $awayCount = $away->participants()->where('status', 'present')->count();
            $incomplete = $homeCount < $meeting->team_size || $awayCount < $meeting->team_size;

            if ($homeCount === 0 || $awayCount === 0 || ($incomplete && ! $allowIncomplete)) {
                throw ValidationException::withMessages(['score' => 'O próximo time está incompleto. Complete a equipe ou confirme a partida incompleta.']);
            }

            $moves = $incomplete || $meeting->team_mode === 'fixed' ? [] : $this->balance->rebalanceIfEligible($meeting, $home, $away);
            $home->update(['status' => 'court', 'queue_position' => null]);
            $away->update(['status' => 'court', 'queue_position' => null]);

            $match = $meeting->matches()->create([
                'home_team_id' => $home->id,
                'away_team_id' => $away->id,
                'sequence' => ((int) $meeting->matches()->max('sequence')) + 1,
                'home_roster' => $this->roster($home),
                'away_roster' => $this->roster($away),
                'incomplete_confirmed' => $incomplete,
                'target_score' => $meeting->target_score,
                'duration_minutes' => $meeting->duration_minutes,
            ]);

            foreach ($moves as $move) {
                $meeting->rosterChanges()->create([
                    'type' => 'balance',
                    'team_id' => $move['target_team_id'],
                    'source_team_id' => $move['source_team_id'],
                    'match_id' => $match->id,
                    'incoming_participant_id' => $move['participant_id'],
                ]);
            }

            return $match;
        });
    }

    public function changeScore(VolleyballMatch $match, string $side, int $change): void
    {
        DB::transaction(function () use ($match, $side, $change): void {
            $match = VolleyballMatch::query()->lockForUpdate()->findOrFail($match->id);
            $column = $side === 'home' ? 'home_score' : 'away_score';

            if ($match->status !== 'playing' || ! Meeting::query()->whereKey($match->meeting_id)->where('status', 'active')->exists() || ! in_array($side, ['home', 'away'], true) || ! in_array($change, [-1, 1], true)) {
                throw ValidationException::withMessages(['score' => 'Alteração de placar inválida.']);
            }

            if ($change === 1 && $match->meeting->sport === 'volleyball' && $this->winner($match) !== null) {
                throw ValidationException::withMessages(['score' => 'A partida terminou. Corrija o placar ou confirme o resultado.']);
            }

            $match->$column = max(0, $match->$column + $change);
            if ($change === 1 && $match->score_started_at === null) {
                $match->score_started_at = now();
            }
            if ($change === 1 && $match->duration_minutes !== null && $match->timer_started_at === null && $match->timer_elapsed_seconds === 0) {
                $match->timer_started_at = now();
            }
            $match->save();
        });
    }

    public function startTimer(VolleyballMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            $match = VolleyballMatch::query()->lockForUpdate()->findOrFail($match->id);
            $this->ensureTimerAvailable($match);

            if ($match->timer_started_at !== null) {
                throw ValidationException::withMessages(['timer' => 'O cronômetro já está em andamento.']);
            }

            $match->update(['timer_started_at' => now()]);
        });
    }

    public function pauseTimer(VolleyballMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            $match = VolleyballMatch::query()->lockForUpdate()->findOrFail($match->id);
            $this->ensureTimerAvailable($match);

            if ($match->timer_started_at === null) {
                throw ValidationException::withMessages(['timer' => 'O cronômetro está parado.']);
            }

            $match->update([
                'timer_elapsed_seconds' => $this->elapsedSeconds($match),
                'timer_started_at' => null,
            ]);
        });
    }

    public function resetTimer(VolleyballMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            $match = VolleyballMatch::query()->lockForUpdate()->findOrFail($match->id);
            $this->ensureTimerAvailable($match);

            if ($match->score_started_at !== null || $match->home_score !== 0 || $match->away_score !== 0) {
                throw ValidationException::withMessages(['timer' => 'O cronômetro só pode ser zerado antes de começar o placar.']);
            }

            $match->update(['timer_started_at' => null, 'timer_elapsed_seconds' => 0]);
        });
    }

    public function refreshUnstartedRoster(VolleyballMatch $match): void
    {
        if ($match->status !== 'playing' || $match->score_started_at !== null || $match->home_score !== 0 || $match->away_score !== 0) {
            throw ValidationException::withMessages(['swap' => 'A escalação só pode ser ajustada antes de começar o placar.']);
        }

        $match->load(['homeTeam', 'awayTeam']);
        $match->update([
            'home_roster' => $this->roster($match->homeTeam),
            'away_roster' => $this->roster($match->awayTeam),
        ]);
    }

    public function winner(VolleyballMatch $match): ?Team
    {
        $home = $match->home_score;
        $away = $match->away_score;

        if ($match->meeting->sport !== 'volleyball') {
            return $home === $away ? null : ($home > $away ? $match->homeTeam : $match->awayTeam);
        }

        $target = $match->target_score;

        if ($home >= $target - 1 && $away >= $target - 1) {
            if (max($home, $away) !== $target + 2 || min($home, $away) > $target + 1) {
                return null;
            }
        } elseif (max($home, $away) !== $target || min($home, $away) > $target - 2) {
            return null;
        }

        return $home > $away ? $match->homeTeam : $match->awayTeam;
    }

    public function confirm(VolleyballMatch $match, bool $allowIncompleteNext = false, ?string $tiebreakWinnerSide = null): void
    {
        DB::transaction(function () use ($match, $allowIncompleteNext, $tiebreakWinnerSide): void {
            $match = VolleyballMatch::query()->with(['homeTeam', 'awayTeam'])->lockForUpdate()->findOrFail($match->id);
            $winner = $this->winner($match);
            $meeting = Meeting::findOrFail($match->meeting_id);

            if ($meeting->sport !== 'volleyball' && $match->home_score === $match->away_score && in_array($tiebreakWinnerSide, ['home', 'away'], true)) {
                $winner = $tiebreakWinnerSide === 'home' ? $match->homeTeam : $match->awayTeam;
            } elseif ($tiebreakWinnerSide !== null) {
                throw ValidationException::withMessages(['score' => 'O desempate só pode ser escolhido quando o placar estiver empatado.']);
            }

            if ($match->status !== 'playing' || $winner === null || $meeting->status !== 'active') {
                throw ValidationException::withMessages(['score' => 'A partida ainda não pode ser confirmada.']);
            }

            $teams = $meeting->teams()->orderBy('id')->get();
            $match->update([
                'status' => 'confirmed',
                'winner_team_id' => $winner->id,
                'queue_snapshot' => $teams->map(fn (Team $team): array => [
                    'id' => $team->id,
                    'status' => $team->status,
                    'queue_position' => $team->queue_position,
                    'consecutive_games' => $team->consecutive_games,
                ])->all(),
                'confirmed_at' => now(),
                'timer_elapsed_seconds' => $this->elapsedSeconds($match),
                'timer_started_at' => null,
            ]);

            $loser = $winner->id === $match->home_team_id ? $match->awayTeam : $match->homeTeam;
            $winner->increment('consecutive_games');
            $loser->increment('consecutive_games');
            [$formedTeam, $unassigned] = $meeting->team_mode === 'dynamic'
                ? $this->formTeamFromUnassigned($meeting)
                : [null, []];

            $outside = $meeting->teams()->where('status', 'waiting')->orderBy('queue_position')->get();

            if ($outside->count() >= 2 && $winner->fresh()->consecutive_games >= 2) {
                $next = $outside->take(2)->values();
                $this->appendToQueue($meeting, $winner);
                $this->appendToQueue($meeting, $loser);
                $rotations = $meeting->team_mode === 'dynamic'
                    ? array_merge(
                        $this->completeFromLoser($meeting, $match, $next[0], $loser),
                        $this->completeFromLoser($meeting, $match, $next[1], $loser),
                    )
                    : [];
                $nextMatch = $this->start($meeting, $next[0], $next[1], $allowIncompleteNext);
                $this->recordAutomaticChanges($meeting, $nextMatch, $formedTeam, $unassigned, $rotations);

                return;
            }

            $this->appendToQueue($meeting, $loser);
            $challenger = $outside->first() ?? $loser;
            $rotations = $meeting->team_mode === 'dynamic' ? $this->completeFromLoser($meeting, $match, $challenger, $loser) : [];
            $nextMatch = $this->start($meeting, $winner, $challenger, $allowIncompleteNext);
            $this->recordAutomaticChanges($meeting, $nextMatch, $formedTeam, $unassigned, $rotations);
        });
    }

    public function promoteWaitingPlayers(Meeting $meeting): void
    {
        DB::transaction(function () use ($meeting): void {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            $current = $meeting->matches()->where('status', 'playing')->lockForUpdate()->firstOrFail();
            $previous = $meeting->matches()->where('status', 'confirmed')->orderByDesc('sequence')->first();
            $unassignedCount = $meeting->participants()->where('status', 'present')->whereNull('team_id')->count();
            $previousLoserId = $previous
                ? ($previous->home_team_id === $previous->winner_team_id ? $previous->away_team_id : $previous->home_team_id)
                : null;

            if ($meeting->status !== 'active' || $meeting->team_mode !== 'dynamic' || ! $previous || $previous->sequence + 1 !== $current->sequence
                || $current->score_started_at !== null || $current->home_score !== 0 || $current->away_score !== 0
                || $previous->winner_team_id !== $current->home_team_id
                || $current->away_team_id !== $previousLoserId
                || $unassignedCount < 1 || $unassignedCount >= $meeting->team_size
                || $meeting->teams()->where('status', 'waiting')->exists()
                || $meeting->rosterChanges()->where('match_id', $current->id)->exists()) {
                throw ValidationException::withMessages(['score' => 'A próxima partida não pode mais ser reorganizada.']);
            }

            $loser = $meeting->teams()->findOrFail($current->away_team_id);
            [$formedTeam, $unassigned] = $this->formTeamFromUnassigned($meeting);

            if (! $formedTeam) {
                throw ValidationException::withMessages(['score' => 'Não há pessoas aguardando para formar outro time.']);
            }

            $rotations = $this->completeFromLoser($meeting, $previous, $formedTeam, $loser);

            if ($formedTeam->participants()->where('status', 'present')->count() !== $meeting->team_size) {
                throw ValidationException::withMessages(['score' => 'Não há jogadores suficientes para completar o próximo time.']);
            }

            $this->appendToQueue($meeting, $loser);
            $formedTeam->update(['status' => 'court', 'queue_position' => null]);
            $current->update(['away_team_id' => $formedTeam->id, 'away_roster' => $this->roster($formedTeam)]);
            $this->recordAutomaticChanges($meeting, $current, $formedTeam, $unassigned, $rotations);
        });
    }

    public function undo(Meeting $meeting): void
    {
        DB::transaction(function () use ($meeting): void {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            if ($meeting->status !== 'active') {
                throw ValidationException::withMessages(['score' => 'Este encontro já foi encerrado.']);
            }

            $latest = $meeting->matches()->where('status', 'confirmed')->orderByDesc('sequence')->first();
            $current = $meeting->matches()->where('status', 'playing')->first();

            if (! $latest || ! $current || $current->sequence !== $latest->sequence + 1 || $current->score_started_at !== null || $current->home_score !== 0 || $current->away_score !== 0) {
                throw ValidationException::withMessages(['score' => 'Não é possível desfazer após alterar o placar seguinte.']);
            }

            $snapshot = $latest->queue_snapshot;

            if ($snapshot === null) {
                throw ValidationException::withMessages(['score' => 'Não é possível desfazer após formar novos times.']);
            }

            $automaticChanges = $meeting->rosterChanges()->where('match_id', $current->id)->get();

            if ($automaticChanges->contains(fn ($change): bool => ! in_array($change->type, ['balance', 'rotation', 'formation'], true))) {
                throw ValidationException::withMessages(['score' => 'Não é possível desfazer após alterar a escalação da partida seguinte.']);
            }

            $snapshotTeamIds = collect($snapshot)->pluck('id');
            $newTeams = $meeting->teams()->whereNotIn('id', $snapshotTeamIds->all())->get();
            $formedTeamIds = $automaticChanges->where('type', 'formation')->pluck('team_id')->unique();

            if ($newTeams->pluck('id')->diff($formedTeamIds)->isNotEmpty() || $formedTeamIds->diff($newTeams->pluck('id'))->isNotEmpty()) {
                throw ValidationException::withMessages(['score' => 'Não é possível desfazer após formar novos times.']);
            }

            foreach (['balance', 'rotation', 'formation'] as $type) {
                foreach ($automaticChanges->where('type', $type) as $change) {
                    Participant::query()->whereKey($change->incoming_participant_id)->where('meeting_id', $meeting->id)
                        ->update(['team_id' => $change->source_team_id]);
                }
            }

            $meeting->rosterChanges()->where('match_id', $current->id)->delete();

            $current->delete();
            $meeting->teams()->whereIn('id', $newTeams->pluck('id')->all())->delete();

            foreach ($snapshot as $state) {
                Team::query()->whereKey($state['id'])->where('meeting_id', $meeting->id)->update([
                    'status' => $state['status'],
                    'queue_position' => $state['queue_position'],
                    'consecutive_games' => $state['consecutive_games'],
                ]);
            }

            $latest->update(['status' => 'playing', 'winner_team_id' => null, 'queue_snapshot' => null, 'confirmed_at' => null]);
        });
    }

    /** @return array<int, array{id: int, name: string}> */
    private function roster(Team $team): array
    {
        return $team->participants()->with('player')->where('status', 'present')->orderBy('arrival_order')->get()
            ->map(fn ($participant): array => ['id' => $participant->player->id, 'name' => $participant->player->name])->all();
    }

    private function appendToQueue(Meeting $meeting, Team $team): void
    {
        $team->update([
            'status' => 'waiting',
            'queue_position' => ((int) $meeting->teams()->where('status', 'waiting')->max('queue_position')) + 1,
            'consecutive_games' => 0,
        ]);
    }

    private function ensureTimerAvailable(VolleyballMatch $match): void
    {
        if ($match->status !== 'playing' || $match->duration_minutes === null || ! Meeting::query()->whereKey($match->meeting_id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['timer' => 'O cronômetro não está disponível para esta partida.']);
        }
    }

    private function elapsedSeconds(VolleyballMatch $match): int
    {
        $runningSeconds = $match->timer_started_at === null
            ? 0
            : max(0, now()->getTimestamp() - $match->timer_started_at->getTimestamp());

        return $match->timer_elapsed_seconds + $runningSeconds;
    }

    /** @return array{Team|null, array<int, Participant>} */
    private function formTeamFromUnassigned(Meeting $meeting): array
    {
        $unassigned = $meeting->participants()->where('status', 'present')->whereNull('team_id')
            ->orderBy('arrival_order')->limit($meeting->team_size)->get();

        if ($unassigned->isEmpty()) {
            return [null, []];
        }

        $team = $meeting->teams()->create([
            'name' => 'Time '.($meeting->teams()->count() + 1),
            'status' => 'waiting',
            'queue_position' => ((int) $meeting->teams()->where('status', 'waiting')->max('queue_position')) + 1,
        ]);
        $meeting->participants()->whereKey($unassigned->pluck('id')->all())->update(['team_id' => $team->id]);

        return [$team, $unassigned->all()];
    }

    /**
     * @return array<int, array{participant_id: int, source_team_id: int, target_team_id: int}>
     */
    private function completeFromLoser(Meeting $meeting, VolleyballMatch $finishedMatch, Team $target, Team $loser): array
    {
        if ($target->id === $loser->id) {
            return [];
        }

        $rotations = [];
        $recentlyBorrowed = $meeting->rosterChanges()->where('match_id', $finishedMatch->id)
            ->whereIn('type', ['rotation', 'loan'])
            ->where('team_id', $loser->id)
            ->where('source_team_id', $target->id)
            ->pluck('incoming_participant_id');

        while ($target->participants()->where('status', 'present')->count() < $meeting->team_size && $loser->participants()->where('status', 'present')->count() > 1) {
            $candidate = $loser->participants()->where('status', 'present')
                ->whereNotIn('id', $recentlyBorrowed->all())
                ->orderBy('arrival_order')->first();

            if (! $candidate) {
                break;
            }

            $candidate->update(['team_id' => $target->id]);
            $rotations[] = [
                'participant_id' => $candidate->id,
                'source_team_id' => $loser->id,
                'target_team_id' => $target->id,
            ];
        }

        return $rotations;
    }

    /**
     * @param  array<int, Participant>  $unassigned
     * @param  array<int, array{participant_id: int, source_team_id: int, target_team_id: int}>  $rotations
     */
    private function recordAutomaticChanges(Meeting $meeting, VolleyballMatch $match, ?Team $formedTeam, array $unassigned, array $rotations): void
    {
        if ($formedTeam) {
            foreach ($unassigned as $participant) {
                $meeting->rosterChanges()->create([
                    'type' => 'formation',
                    'team_id' => $formedTeam->id,
                    'match_id' => $match->id,
                    'incoming_participant_id' => $participant->id,
                ]);
            }
        }

        foreach ($rotations as $rotation) {
            $meeting->rosterChanges()->create([
                'type' => 'rotation',
                'team_id' => $rotation['target_team_id'],
                'source_team_id' => $rotation['source_team_id'],
                'match_id' => $match->id,
                'incoming_participant_id' => $rotation['participant_id'],
            ]);
        }
    }
}
