<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamFormationService
{
    public function __construct(private MatchService $matches, private SubstitutionService $substitutions, private TeamBalanceService $balance) {}

    public function arrive(Meeting $meeting, string $name, string $gender, int $skillLevel): Participant
    {
        return DB::transaction(function () use ($meeting, $name, $gender, $skillLevel): Participant {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            $this->ensureActive($meeting);
            $player = $meeting->user->players()->create(['name' => trim($name), 'gender' => $gender, 'skill_level' => $skillLevel]);

            return $this->registerPlayer($meeting, $player);
        });
    }

    public function arriveExisting(Meeting $meeting, int $playerId): Participant
    {
        return DB::transaction(function () use ($meeting, $playerId): Participant {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            $this->ensureActive($meeting);

            return $this->registerPlayer($meeting, Player::query()->where('user_id', $meeting->user_id)->findOrFail($playerId));
        });
    }

    public function formAvailableTeams(Meeting $meeting): void
    {
        $waiting = $meeting->participants()->with('player')->where('status', 'present')
            ->whereNull('team_id')->orderBy('arrival_order')->get();
        $teamSize = $meeting->team_size;

        while ($waiting->count() >= $teamSize) {
            if ($meeting->teams()->count() === 1 && ! $meeting->matches()->exists()) {
                $firstTeam = $meeting->teams()->firstOrFail();
                $firstMembers = $firstTeam->participants()->with('player')->orderBy('arrival_order')->get();
                $selected = $firstMembers->concat($waiting->splice(0, $teamSize))->sortBy('arrival_order')->values();
                [$first, $second] = $this->balance->balancedPair($meeting, $selected, $teamSize);
                Participant::query()->where('team_id', $firstTeam->id)->update(['team_id' => null]);
                Participant::query()->whereKey($first->pluck('id')->all())->update(['team_id' => $firstTeam->id]);
                $this->createTeam($meeting, $second);
            } else {
                $this->createTeam($meeting, $waiting->splice(0, $teamSize));
            }
        }

        if (! $meeting->matches()->where('status', 'playing')->exists()) {
            $teams = $meeting->teams()->where('status', 'waiting')->orderBy('queue_position')->limit(2)->get();

            if ($teams->count() === 2) {
                $this->matches->start($meeting, $teams[0], $teams[1]);
            }
        }
    }

    /** @param Collection<int, Participant> $members */
    private function createTeam(Meeting $meeting, Collection $members): Team
    {
        $number = $meeting->teams()->count() + 1;
        $team = $meeting->teams()->create([
            'name' => 'Time '.$number,
            'status' => 'waiting',
            'queue_position' => $number,
        ]);

        Participant::query()->whereKey($members->pluck('id')->all())->update(['team_id' => $team->id]);

        return $team;
    }

    private function ensureActive(Meeting $meeting): void
    {
        if ($meeting->status !== 'active') {
            throw ValidationException::withMessages(['name' => 'Este encontro já foi encerrado.']);
        }
    }

    private function registerPlayer(Meeting $meeting, Player $player): Participant
    {
        $participant = $meeting->participants()->where('player_id', $player->id)->lockForUpdate()->first();

        if ($participant?->status === 'present') {
            throw ValidationException::withMessages(['existingPlayerId' => 'Esta pessoa já está presente no encontro.']);
        }

        $arrivalOrder = ((int) $meeting->participants()->max('arrival_order')) + 1;

        if ($participant) {
            $participant->update(['arrival_order' => $arrivalOrder, 'status' => 'present', 'left_at' => null, 'team_id' => null]);
            $meeting->rosterChanges()->create([
                'type' => 'return',
                'match_id' => $meeting->matches()->where('status', 'playing')->value('id'),
                'incoming_participant_id' => $participant->id,
            ]);
        } else {
            $participant = $meeting->participants()->create([
                'player_id' => $player->id,
                'arrival_order' => $arrivalOrder,
                'status' => 'present',
            ]);
        }

        $this->substitutions->fillVacancies($meeting);
        $this->formAvailableTeams($meeting);

        return $participant->refresh();
    }
}
