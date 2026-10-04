<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\Team;
use Illuminate\Support\Collection;

class TeamBalanceService
{
    /**
     * @param  Collection<int, Participant>  $selected
     * @return array{Collection<int, Participant>, Collection<int, Participant>}
     */
    public function balancedPair(Meeting $meeting, Collection $selected, int $teamSize): array
    {
        $players = $selected->toBase()->values();
        $best = PHP_INT_MAX;
        $bestMask = 0;
        $count = $players->count();
        $femaleFlags = [];
        $skillLevels = [];

        foreach ($players as $player) {
            $femaleFlags[] = $player->player->gender === 'female' ? 1 : 0;
            $skillLevels[] = $player->player->skill_level;
        }

        $totalFemale = array_sum($femaleFlags);
        $totalSkill = array_sum($skillLevels);

        $states = [1 => [$femaleFlags[0] => [$skillLevels[0] => 1]]];

        for ($index = 1; $index < $count; $index++) {
            for ($selectedCount = min($teamSize - 1, $index); $selectedCount >= 1; $selectedCount--) {
                foreach ($states[$selectedCount] ?? [] as $femaleCount => $skillMasks) {
                    foreach ($skillMasks as $skillTotal => $mask) {
                        $nextFemale = $femaleCount + $femaleFlags[$index];
                        $nextSkill = $skillTotal + $skillLevels[$index];
                        $nextMask = $mask | (1 << $index);
                        $storedMask = $states[$selectedCount + 1][$nextFemale][$nextSkill] ?? null;

                        if ($storedMask === null || $nextMask < $storedMask) {
                            $states[$selectedCount + 1][$nextFemale][$nextSkill] = $nextMask;
                        }
                    }
                }
            }
        }

        foreach ($states[$teamSize] as $femaleCount => $skillMasks) {
            foreach ($skillMasks as $skillTotal => $mask) {
                $score = abs(2 * $femaleCount - $totalFemale) * $meeting->gender_weight
                    + abs(2 * $skillTotal - $totalSkill) * $meeting->skill_weight;

                if ($score < $best || ($score === $best && $mask < $bestMask)) {
                    $best = $score;
                    $bestMask = $mask;
                }
            }
        }

        $firstIndexes = [];

        for ($index = 0; $index < $count; $index++) {
            if ($bestMask & (1 << $index)) {
                $firstIndexes[] = $index;
            }
        }

        return [$players->only($firstIndexes)->values(), $players->except($firstIndexes)->values()];
    }

    /**
     * @return array<int, array{participant_id: int, source_team_id: int, target_team_id: int}>
     */
    public function rebalanceIfEligible(Meeting $meeting, Team $home, Team $away): array
    {
        if ($home->status !== 'waiting' || $away->status !== 'waiting') {
            return [];
        }

        $teamIds = [$home->id, $away->id];

        if ($meeting->matches()->where(function ($query) use ($teamIds): void {
            $query->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds);
        })->exists()) {
            return [];
        }

        $homeMembers = $this->members($home);
        $awayMembers = $this->members($away);

        if ($homeMembers->count() !== $meeting->team_size || $awayMembers->count() !== $meeting->team_size) {
            return [];
        }

        $selected = $homeMembers->concat($awayMembers)->sortBy('arrival_order')->values();
        [$first, $second] = $this->balancedPair($meeting, $selected, $meeting->team_size);

        if ($this->score($meeting, $first, $second) >= $this->score($meeting, $homeMembers, $awayMembers)) {
            return [];
        }

        return array_merge($this->moveMembers($first, $home), $this->moveMembers($second, $away));
    }

    /**
     * @return array{home_female: int, away_female: int, home_skill: int, away_skill: int, gender_gap: int, skill_gap: int}
     */
    public function describe(Team $home, Team $away): array
    {
        $homeMembers = $this->members($home);
        $awayMembers = $this->members($away);
        $homeFemale = $homeMembers->where('player.gender', 'female')->count();
        $awayFemale = $awayMembers->where('player.gender', 'female')->count();
        $homeSkill = (int) $homeMembers->sum('player.skill_level');
        $awaySkill = (int) $awayMembers->sum('player.skill_level');

        return [
            'home_female' => $homeFemale,
            'away_female' => $awayFemale,
            'home_skill' => $homeSkill,
            'away_skill' => $awaySkill,
            'gender_gap' => abs($homeFemale - $awayFemale),
            'skill_gap' => abs($homeSkill - $awaySkill),
        ];
    }

    /** @return Collection<int, Participant> */
    private function members(Team $team): Collection
    {
        return $team->participants()->with('player')->where('status', 'present')->orderBy('arrival_order')->get()->toBase();
    }

    /**
     * @param  Collection<int, Participant>  $members
     * @return array<int, array{participant_id: int, source_team_id: int, target_team_id: int}>
     */
    private function moveMembers(Collection $members, Team $target): array
    {
        $moves = [];

        foreach ($members as $participant) {
            if ($participant->team_id === $target->id) {
                continue;
            }

            $moves[] = [
                'participant_id' => $participant->id,
                'source_team_id' => $participant->team_id,
                'target_team_id' => $target->id,
            ];
            $participant->update(['team_id' => $target->id]);
        }

        return $moves;
    }

    /**
     * @param  Collection<int, Participant>  $first
     * @param  Collection<int, Participant>  $second
     */
    private function score(Meeting $meeting, Collection $first, Collection $second): int
    {
        $genderGap = abs($first->where('player.gender', 'female')->count() - $second->where('player.gender', 'female')->count());
        $skillGap = abs((int) $first->sum('player.skill_level') - (int) $second->sum('player.skill_level'));

        return $genderGap * $meeting->gender_weight + $skillGap * $meeting->skill_weight;
    }
}
