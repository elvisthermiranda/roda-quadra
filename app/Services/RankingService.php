<?php

namespace App\Services;

use App\Models\Meeting;
use Illuminate\Support\Collection;

class RankingService
{
    /**
     * @return Collection<int, array{team_id: int, name: string, played: int, wins: int, losses: int, win_rate: float, points_for: int, points_against: int, point_balance: int}>
     */
    public function forMeeting(Meeting $meeting): Collection
    {
        $ranking = [];
        $matches = $meeting->matches()->where('status', 'confirmed')->get();

        foreach ($meeting->teams()->orderBy('id')->get() as $team) {
            $played = 0;
            $wins = 0;
            $pointsFor = 0;
            $pointsAgainst = 0;

            foreach ($matches as $match) {
                if ($match->home_team_id === $team->id) {
                    $played++;
                    $pointsFor += $match->home_score;
                    $pointsAgainst += $match->away_score;
                } elseif ($match->away_team_id === $team->id) {
                    $played++;
                    $pointsFor += $match->away_score;
                    $pointsAgainst += $match->home_score;
                } else {
                    continue;
                }

                if ($match->winner_team_id === $team->id) {
                    $wins++;
                }
            }

            $ranking[] = [
                'team_id' => $team->id,
                'name' => $team->name,
                'played' => $played,
                'wins' => $wins,
                'losses' => $played - $wins,
                'win_rate' => $played > 0 ? round($wins / $played, 4) : 0.0,
                'points_for' => $pointsFor,
                'points_against' => $pointsAgainst,
                'point_balance' => $pointsFor - $pointsAgainst,
            ];
        }

        $order = ['wins', $meeting->ranking_tiebreaker, $meeting->ranking_tiebreaker === 'win_rate' ? 'point_balance' : 'win_rate'];

        return collect($ranking)->sort(function (array $first, array $second) use ($order): int {
            foreach ($order as $field) {
                $comparison = match ($field) {
                    'wins' => $second['wins'] <=> $first['wins'],
                    'win_rate' => $second['win_rate'] <=> $first['win_rate'],
                    'point_balance' => $second['point_balance'] <=> $first['point_balance'],
                    default => 0,
                };

                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return $first['team_id'] <=> $second['team_id'];
        })->values();
    }
}
