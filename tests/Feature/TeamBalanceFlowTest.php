<?php

use App\Models\Meeting;
use App\Models\User;
use App\Services\MatchService;
use App\Services\TeamBalanceService;
use App\Services\TeamFormationService;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

function meetingWithUnevenWaitingTeams(): Meeting
{
    $meeting = Meeting::factory()->create();
    $formation = app(TeamFormationService::class);

    for ($order = 1; $order <= 12; $order++) {
        $formation->arrive($meeting, 'Pessoa '.$order, $order % 2 === 0 ? 'female' : 'male', 3);
    }

    for ($order = 13; $order <= 16; $order++) {
        $formation->arrive($meeting, 'Pessoa '.$order, 'male', 5);
    }

    for ($order = 17; $order <= 20; $order++) {
        $formation->arrive($meeting, 'Pessoa '.$order, 'female', 1);
    }

    return $meeting;
}

function advanceTwoHomeWins(Meeting $meeting): void
{
    $matches = app(MatchService::class);

    for ($game = 0; $game < 2; $game++) {
        $match = $meeting->matches()->where('status', 'playing')->firstOrFail();
        $match->update(['home_score' => 15]);
        $matches->confirm($match);
    }
}

test('two fresh teams are balanced when they enter together without bypassing earlier arrivals', function () {
    $meeting = meetingWithUnevenWaitingTeams();
    $fourth = $meeting->teams()->where('name', 'Time 4')->firstOrFail();
    $fifth = $meeting->teams()->where('name', 'Time 5')->firstOrFail();
    $balance = app(TeamBalanceService::class);

    expect($balance->describe($fourth, $fifth)['gender_gap'])->toBe(4);

    advanceTwoHomeWins($meeting);

    $current = $meeting->matches()->where('status', 'playing')->firstOrFail();
    $summary = $balance->describe($fourth, $fifth);
    $arrivalOrders = $fourth->participants()->pluck('arrival_order')
        ->merge($fifth->participants()->pluck('arrival_order'))->sort()->values()->all();

    expect([$current->home_team_id, $current->away_team_id])->toBe([$fourth->id, $fifth->id]);
    expect($summary['home_female'])->toBe(2);
    expect($summary['away_female'])->toBe(2);
    expect($summary['skill_gap'])->toBe(0);
    expect($arrivalOrders)->toBe(range(13, 20));
    expect($meeting->rosterChanges()->where('type', 'balance')->count())->toBeGreaterThan(0);
});

test('a new challenger keeps its original roster when paired with a team that has played', function () {
    $meeting = Meeting::factory()->create();
    $formation = app(TeamFormationService::class);

    for ($order = 1; $order <= 8; $order++) {
        $formation->arrive($meeting, 'Pessoa '.$order, $order % 2 === 0 ? 'female' : 'male', 3);
    }

    for ($order = 9; $order <= 12; $order++) {
        $formation->arrive($meeting, 'Pessoa '.$order, 'male', 5);
    }

    for ($order = 13; $order <= 16; $order++) {
        $formation->arrive($meeting, 'Pessoa '.$order, 'female', 1);
    }

    $first = $meeting->matches()->firstOrFail();
    $first->update(['home_score' => 15]);
    app(MatchService::class)->confirm($first);
    $challenger = $meeting->teams()->where('name', 'Time 3')->firstOrFail();

    expect($meeting->matches()->where('status', 'playing')->firstOrFail()->away_team_id)->toBe($challenger->id);
    expect($challenger->participants()->whereHas('player', fn ($query) => $query->where('gender', 'male'))->count())->toBe(4);
    expect($meeting->rosterChanges()->where('type', 'balance')->count())->toBe(0);
});

test('undo removes automatic balancing and restores each team composition', function () {
    $meeting = meetingWithUnevenWaitingTeams();
    $fourth = $meeting->teams()->where('name', 'Time 4')->firstOrFail();
    $fifth = $meeting->teams()->where('name', 'Time 5')->firstOrFail();
    advanceTwoHomeWins($meeting);

    app(MatchService::class)->undo($meeting);

    $summary = app(TeamBalanceService::class)->describe($fourth, $fifth);
    expect($summary['home_female'])->toBe(0);
    expect($summary['away_female'])->toBe(4);
    expect($meeting->rosterChanges()->where('type', 'balance')->count())->toBe(0);
    expect($meeting->matches()->count())->toBe(2);
    expect($meeting->matches()->where('sequence', 2)->firstOrFail()->status)->toBe('playing');
});

test('formation weights can favor ability over gender distribution', function () {
    $meeting = Meeting::factory()->create(['gender_weight' => 0]);
    $formation = app(TeamFormationService::class);

    for ($order = 1; $order <= 4; $order++) {
        $formation->arrive($meeting, 'Pessoa '.$order, 'male', $order === 4 ? 1 : 5);
    }

    for ($order = 5; $order <= 8; $order++) {
        $formation->arrive($meeting, 'Pessoa '.$order, 'female', 2);
    }

    $match = $meeting->matches()->firstOrFail();
    $summary = app(TeamBalanceService::class)->describe($match->homeTeam, $match->awayTeam);

    expect($summary['gender_gap'])->toBe(2);
    expect($summary['skill_gap'])->toBe(2);
});

test('first-match balancing works in larger formats', function (int $teamSize, int $expectedGenderGap) {
    $meeting = Meeting::factory()->create(['team_size' => $teamSize]);
    $formation = app(TeamFormationService::class);

    for ($order = 1; $order <= 2 * $teamSize; $order++) {
        $formation->arrive($meeting, 'Pessoa '.$order, $order % 2 === 0 ? 'female' : 'male', 3);
    }

    $match = $meeting->matches()->firstOrFail();
    $summary = app(TeamBalanceService::class)->describe($match->homeTeam, $match->awayTeam);

    expect($match->home_roster)->toHaveCount($teamSize);
    expect($match->away_roster)->toHaveCount($teamSize);
    expect($summary['gender_gap'])->toBe($expectedGenderGap);
})->with([[2, 0], [3, 1], [5, 1], [6, 0], [10, 0], [11, 1]]);
