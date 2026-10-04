<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubstitutionService
{
    public function depart(Meeting $meeting, int $participantId): void
    {
        DB::transaction(function () use ($meeting, $participantId): void {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            $this->ensureActive($meeting);
            $participant = $meeting->participants()->with('team')->lockForUpdate()->findOrFail($participantId);

            if ($participant->status !== 'present') {
                throw ValidationException::withMessages(['participant' => 'Esta pessoa já saiu do encontro.']);
            }

            $participant->update(['status' => 'left', 'left_at' => now()]);
            $meeting->rosterChanges()->create([
                'type' => 'departure',
                'team_id' => $participant->team_id,
                'match_id' => $this->currentMatchId($meeting),
                'outgoing_participant_id' => $participant->id,
            ]);

            $this->fillVacancies($meeting);
        });
    }

    public function fillVacancies(Meeting $meeting): void
    {
        $this->ensureActive($meeting);

        $teams = $meeting->teams()->get()->sortBy(fn (Team $team): array => [
            $team->status === 'court' ? 0 : 1,
            $team->queue_position ?? 0,
            $team->id,
        ]);

        foreach ($teams as $team) {
            while ($team->participants()->where('status', 'present')->count() < $meeting->team_size) {
                $candidate = $meeting->participants()->where('status', 'present')
                    ->whereNull('team_id')->orderBy('arrival_order')->first();

                if (! $candidate) {
                    return;
                }

                $candidate->update(['team_id' => $team->id]);
                $meeting->rosterChanges()->create([
                    'type' => 'replacement',
                    'team_id' => $team->id,
                    'match_id' => $this->currentMatchId($meeting),
                    'incoming_participant_id' => $candidate->id,
                ]);
                $this->addToCurrentRoster($meeting, $team, $candidate);
            }
        }
    }

    public function suggestLoan(Meeting $meeting, Team $target): ?Participant
    {
        if ($target->meeting_id !== $meeting->id || $target->participants()->where('status', 'present')->count() >= $meeting->team_size) {
            return null;
        }

        if ($meeting->participants()->where('status', 'present')->whereNull('team_id')->exists()) {
            return null;
        }

        return $meeting->participants()->with(['player', 'team'])
            ->where('status', 'present')
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $target->id)
            ->whereHas('team', fn ($query) => $query->where('status', 'waiting')
                ->whereHas('participants', fn ($members) => $members->where('status', 'present'), '>=', $meeting->team_size))
            ->orderBy('arrival_order')->first();
    }

    public function loan(Meeting $meeting, int $targetTeamId, int $participantId): void
    {
        DB::transaction(function () use ($meeting, $targetTeamId, $participantId): void {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            $this->ensureActive($meeting);
            $target = $meeting->teams()->findOrFail($targetTeamId);
            $suggestion = $this->suggestLoan($meeting, $target);

            if ($suggestion?->id !== $participantId) {
                throw ValidationException::withMessages(['participant' => 'O empréstimo sugerido mudou. Confira a fila novamente.']);
            }

            $sourceTeamId = $suggestion->team_id;
            $suggestion->update(['team_id' => $target->id]);
            $meeting->rosterChanges()->create([
                'type' => 'loan',
                'team_id' => $target->id,
                'source_team_id' => $sourceTeamId,
                'match_id' => $this->currentMatchId($meeting),
                'incoming_participant_id' => $suggestion->id,
            ]);
            $this->addToCurrentRoster($meeting, $target, $suggestion);
        });
    }

    private function ensureActive(Meeting $meeting): void
    {
        if ($meeting->status !== 'active') {
            throw ValidationException::withMessages(['participant' => 'Este encontro já foi encerrado.']);
        }
    }

    private function currentMatchId(Meeting $meeting): ?int
    {
        return $meeting->matches()->where('status', 'playing')->value('id');
    }

    private function addToCurrentRoster(Meeting $meeting, Team $team, Participant $participant): void
    {
        if ($team->status !== 'court') {
            return;
        }

        $match = $meeting->matches()->where('status', 'playing')->first();

        if (! $match) {
            return;
        }

        $column = $match->home_team_id === $team->id ? 'home_roster' : 'away_roster';
        $roster = $match->$column;

        if (! collect($roster)->contains('id', $participant->player_id)) {
            $roster[] = ['id' => $participant->player_id, 'name' => $participant->player->name];
            $match->$column = $roster;
            $match->save();
        }
    }
}
