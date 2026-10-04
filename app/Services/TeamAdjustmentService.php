<?php

namespace App\Services;

use App\Models\Meeting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamAdjustmentService
{
    public function __construct(private MatchService $matches) {}

    public function swap(Meeting $meeting, int $firstParticipantId, int $secondParticipantId): void
    {
        DB::transaction(function () use ($meeting, $firstParticipantId, $secondParticipantId): void {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            $match = $meeting->matches()->where('status', 'playing')->lockForUpdate()->firstOrFail();

            if ($meeting->status !== 'active' || $match->score_started_at !== null || $match->home_score !== 0 || $match->away_score !== 0 || $meeting->rosterChanges()->where('match_id', $match->id)->where('type', '!=', 'swap')->exists()) {
                throw ValidationException::withMessages(['swap' => 'A troca só é permitida antes de começar o placar e de outras mudanças na escalação.']);
            }

            if ($firstParticipantId === $secondParticipantId) {
                throw ValidationException::withMessages(['swap' => 'Escolha duas pessoas de times diferentes.']);
            }

            $first = $meeting->participants()->where('status', 'present')->lockForUpdate()->findOrFail($firstParticipantId);
            $second = $meeting->participants()->where('status', 'present')->lockForUpdate()->findOrFail($secondParticipantId);
            $courtTeamIds = [$match->home_team_id, $match->away_team_id];

            if ($first->team_id === $second->team_id || ! in_array($first->team_id, $courtTeamIds, true) || ! in_array($second->team_id, $courtTeamIds, true)) {
                throw ValidationException::withMessages(['swap' => 'A troca deve envolver um jogador de cada time em quadra.']);
            }

            $firstTeamId = $first->team_id;
            $secondTeamId = $second->team_id;
            $first->update(['team_id' => $secondTeamId]);
            $second->update(['team_id' => $firstTeamId]);
            $this->matches->refreshUnstartedRoster($match);

            $meeting->rosterChanges()->create([
                'type' => 'swap',
                'team_id' => $firstTeamId,
                'source_team_id' => $secondTeamId,
                'match_id' => $match->id,
                'outgoing_participant_id' => $first->id,
                'incoming_participant_id' => $second->id,
            ]);
        });
    }
}
