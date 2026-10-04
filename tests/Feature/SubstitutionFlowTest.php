<?php

use App\Models\Meeting;
use App\Models\User;
use App\Services\MatchService;
use App\Services\SubstitutionService;
use App\Services\TeamFormationService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

function registerSubstitutionPlayers(Meeting $meeting, int $count): void
{
    $formation = app(TeamFormationService::class);

    for ($order = 1; $order <= $count; $order++) {
        $formation->arrive($meeting, 'Jogador '.$order, $order % 2 === 0 ? 'female' : 'male', 3);
    }
}

test('departure fills the vacancy with the earliest unassigned arrival and keeps the match roster', function () {
    $meeting = Meeting::factory()->create();
    registerSubstitutionPlayers($meeting, 10);
    $departing = $meeting->participants()->where('arrival_order', 1)->firstOrFail();
    $teamId = $departing->team_id;

    app(SubstitutionService::class)->depart($meeting, $departing->id);

    expect($departing->fresh()->status)->toBe('left');
    expect($meeting->participants()->where('arrival_order', 9)->firstOrFail()->team_id)->toBe($teamId);
    expect($meeting->participants()->where('arrival_order', 10)->firstOrFail()->team_id)->toBeNull();
    expect($meeting->teams()->findOrFail($teamId)->participants()->where('status', 'present')->count())->toBe(4);
    expect($meeting->rosterChanges()->orderBy('id')->pluck('type')->all())->toBe(['departure', 'replacement']);
    expect(collect($meeting->matches()->firstOrFail()->home_roster)->pluck('name')->all())->toContain('Jogador 1', 'Jogador 9');
});

test('a loan requires the suggested player from a waiting team and records its impact', function () {
    $meeting = Meeting::factory()->create();
    registerSubstitutionPlayers($meeting, 12);
    $departing = $meeting->participants()->where('arrival_order', 1)->firstOrFail();
    $substitutions = app(SubstitutionService::class);
    $substitutions->depart($meeting, $departing->id);
    $target = $meeting->teams()->findOrFail($departing->team_id);
    $suggestion = $substitutions->suggestLoan($meeting, $target);

    expect($suggestion?->arrival_order)->toBe(9);
    $sourceTeamId = $suggestion->team_id;
    expect(fn () => $substitutions->loan($meeting, $target->id, $meeting->participants()->where('arrival_order', 10)->firstOrFail()->id))->toThrow(ValidationException::class);

    $substitutions->loan($meeting, $target->id, $suggestion->id);

    expect($suggestion->fresh()->team_id)->toBe($target->id);
    expect($meeting->teams()->findOrFail($sourceTeamId)->participants()->where('status', 'present')->count())->toBe(3);
    expect($meeting->rosterChanges()->where('type', 'loan')->firstOrFail()->source_team_id)->toBe($sourceTeamId);
});

test('a new arrival fills the team affected by a loan before forming another team', function () {
    $meeting = Meeting::factory()->create();
    registerSubstitutionPlayers($meeting, 12);
    $departing = $meeting->participants()->where('arrival_order', 1)->firstOrFail();
    $substitutions = app(SubstitutionService::class);
    $substitutions->depart($meeting, $departing->id);
    $target = $meeting->teams()->findOrFail($departing->team_id);
    $sourceTeamId = $substitutions->suggestLoan($meeting, $target)->team_id;
    $substitutions->loan($meeting, $target->id, $substitutions->suggestLoan($meeting, $target)->id);

    $arrival = app(TeamFormationService::class)->arrive($meeting, 'Jogador 13', 'female', 3);

    expect($arrival->team_id)->toBe($sourceTeamId);
    expect($meeting->teams()->count())->toBe(3);
});

test('starting an incomplete next team needs explicit confirmation', function () {
    $meeting = Meeting::factory()->create();
    registerSubstitutionPlayers($meeting, 8);
    $substitutions = app(SubstitutionService::class);
    $match = $meeting->matches()->firstOrFail();
    $departing = $meeting->participants()->where('team_id', $match->away_team_id)->firstOrFail();
    $substitutions->depart($meeting, $departing->id);
    $match->update(['home_score' => 15]);
    $matches = app(MatchService::class);

    expect(fn () => $matches->confirm($match))->toThrow(ValidationException::class);
    expect($match->fresh()->status)->toBe('playing');
    expect($meeting->matches()->count())->toBe(1);

    $matches->confirm($match, true);

    $next = $meeting->matches()->where('status', 'playing')->firstOrFail();
    expect($next->incomplete_confirmed)->toBeTrue();
    expect($next->away_roster)->toHaveCount(3);
});

test('the last result cannot be undone after a roster change in the following match', function () {
    $meeting = Meeting::factory()->create();
    registerSubstitutionPlayers($meeting, 9);
    $match = $meeting->matches()->firstOrFail();
    $match->update(['home_score' => 15]);
    $matches = app(MatchService::class);
    $matches->confirm($match);
    $current = $meeting->matches()->where('status', 'playing')->firstOrFail();
    $departing = $meeting->participants()->where('team_id', $current->home_team_id)->where('status', 'present')->firstOrFail();
    app(SubstitutionService::class)->depart($meeting, $departing->id);

    expect(fn () => $matches->undo($meeting))->toThrow(ValidationException::class);
    expect($meeting->matches()->count())->toBe(2);
});

test('the dashboard exposes the loan impact and records a confirmed loan', function () {
    $meeting = Meeting::factory()->create();
    registerSubstitutionPlayers($meeting, 12);
    $departing = $meeting->participants()->where('arrival_order', 1)->firstOrFail();

    Livewire::test('pages::dashboard')
        ->call('departPlayer', $departing->id)
        ->assertSee('Times incompletos')
        ->assertSee('Confirmar empréstimo')
        ->call('loanPlayer', $departing->team_id, $meeting->participants()->where('arrival_order', 9)->firstOrFail()->id)
        ->assertHasNoErrors();

    expect($meeting->rosterChanges()->where('type', 'loan')->count())->toBe(1);
});
