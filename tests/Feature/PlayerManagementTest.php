<?php

use App\Models\Meeting;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchService;
use App\Services\SubstitutionService;
use App\Services\TeamAdjustmentService;
use App\Services\TeamFormationService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

function registerManagementPlayers(Meeting $meeting, int $count): void
{
    $formation = app(TeamFormationService::class);

    for ($order = 1; $order <= $count; $order++) {
        $formation->arrive($meeting, 'Atleta '.$order, $order % 2 === 0 ? 'female' : 'male', 3);
    }
}

test('a registered player is reused across meetings without another player record', function () {
    $player = Player::factory()->create(['name' => 'Ana']);
    $firstMeeting = Meeting::factory()->create();
    $secondMeeting = Meeting::factory()->create();
    $formation = app(TeamFormationService::class);

    $formation->arriveExisting($firstMeeting, $player->id);
    $formation->arriveExisting($secondMeeting, $player->id);

    expect(Player::count())->toBe(1);
    expect($player->participants()->count())->toBe(2);
    expect($firstMeeting->participants()->firstOrFail()->player_id)->toBe($secondMeeting->participants()->firstOrFail()->player_id);
});

test('a player cannot enter twice and returns at the end after leaving', function () {
    $meeting = Meeting::factory()->create();
    $player = Player::factory()->create(['name' => 'Ana']);
    $formation = app(TeamFormationService::class);
    $firstArrival = $formation->arriveExisting($meeting, $player->id);
    $formation->arrive($meeting, 'Bruna', 'female', 3);

    expect(fn () => $formation->arriveExisting($meeting, $player->id))->toThrow(ValidationException::class);

    app(SubstitutionService::class)->depart($meeting, $firstArrival->id);
    $return = $formation->arriveExisting($meeting, $player->id);

    expect($return->id)->toBe($firstArrival->id);
    expect($return->arrival_order)->toBe(3);
    expect($return->status)->toBe('present');
    expect($return->left_at)->toBeNull();
    expect($meeting->participants()->count())->toBe(2);
    expect($meeting->rosterChanges()->orderBy('id')->pluck('type')->all())->toBe(['departure', 'return']);
});

test('a returning player can refill a court vacancy without appearing twice in the match roster', function () {
    $meeting = Meeting::factory()->create();
    registerManagementPlayers($meeting, 8);
    $match = $meeting->matches()->firstOrFail();
    $participant = $meeting->participants()->where('arrival_order', 1)->firstOrFail();
    app(SubstitutionService::class)->depart($meeting, $participant->id);

    $return = app(TeamFormationService::class)->arriveExisting($meeting, $participant->player_id);

    expect($return->team_id)->toBe($match->home_team_id);
    expect($return->arrival_order)->toBe(9);
    expect(collect($match->fresh()->home_roster)->where('id', $participant->player_id)->count())->toBe(1);
    expect($meeting->rosterChanges()->orderBy('id')->pluck('type')->all())->toBe(['departure', 'return', 'replacement']);
});

test('a swap before the first point updates both teams and the match roster', function () {
    $meeting = Meeting::factory()->create();
    registerManagementPlayers($meeting, 8);
    $match = $meeting->matches()->firstOrFail();
    $home = $meeting->participants()->where('team_id', $match->home_team_id)->firstOrFail();
    $away = $meeting->participants()->where('team_id', $match->away_team_id)->firstOrFail();

    app(TeamAdjustmentService::class)->swap($meeting, $home->id, $away->id);

    expect($home->fresh()->team_id)->toBe($match->away_team_id);
    expect($away->fresh()->team_id)->toBe($match->home_team_id);
    expect(collect($match->fresh()->home_roster)->pluck('id')->all())->toContain($away->player_id);
    expect(collect($match->fresh()->home_roster)->pluck('id')->all())->not->toContain($home->player_id);
    expect($meeting->rosterChanges()->firstOrFail()->type)->toBe('swap');

    app(TeamAdjustmentService::class)->swap($meeting, $home->id, $away->id);
    expect($home->fresh()->team_id)->toBe($match->home_team_id);
    expect($meeting->rosterChanges()->where('type', 'swap')->count())->toBe(2);
});

test('a swap is refused after a point even when the score was corrected back to zero', function () {
    $meeting = Meeting::factory()->create();
    registerManagementPlayers($meeting, 8);
    $match = $meeting->matches()->firstOrFail();
    $home = $meeting->participants()->where('team_id', $match->home_team_id)->firstOrFail();
    $away = $meeting->participants()->where('team_id', $match->away_team_id)->firstOrFail();
    $matches = app(MatchService::class);
    $matches->changeScore($match, 'home', 1);
    $matches->changeScore($match, 'home', -1);

    expect(fn () => app(TeamAdjustmentService::class)->swap($meeting, $home->id, $away->id))->toThrow(ValidationException::class);
    expect($home->fresh()->team_id)->toBe($match->home_team_id);
    expect($meeting->rosterChanges()->count())->toBe(0);
});

test('an in-progress match created before score tracking cannot have its teams swapped', function () {
    $meeting = Meeting::factory()->create();
    registerManagementPlayers($meeting, 8);
    $match = $meeting->matches()->firstOrFail();
    $home = $meeting->participants()->where('team_id', $match->home_team_id)->firstOrFail();
    $away = $meeting->participants()->where('team_id', $match->away_team_id)->firstOrFail();
    $match->update(['home_score' => 1, 'score_started_at' => null]);

    expect(fn () => app(TeamAdjustmentService::class)->swap($meeting, $home->id, $away->id))->toThrow(ValidationException::class);
    expect($home->fresh()->team_id)->toBe($match->home_team_id);
});

test('a player from the waiting team cannot replace an earlier arrival through a manual swap', function () {
    $meeting = Meeting::factory()->create();
    registerManagementPlayers($meeting, 12);
    $match = $meeting->matches()->firstOrFail();
    $home = $meeting->participants()->where('team_id', $match->home_team_id)->firstOrFail();
    $waiting = $meeting->participants()->where('arrival_order', 9)->firstOrFail();

    expect(fn () => app(TeamAdjustmentService::class)->swap($meeting, $home->id, $waiting->id))->toThrow(ValidationException::class);
    expect($waiting->fresh()->team_id)->toBe($waiting->team_id);
});

test('the dashboard reuses a registered player and offers the pregame swap', function () {
    $meeting = Meeting::factory()->create();
    registerManagementPlayers($meeting, 8);
    $player = Player::factory()->create(['name' => 'Visitante']);
    $match = $meeting->matches()->firstOrFail();
    $home = $meeting->participants()->where('team_id', $match->home_team_id)->firstOrFail();
    $away = $meeting->participants()->where('team_id', $match->away_team_id)->firstOrFail();

    Livewire::test('pages::dashboard')
        ->assertSee('Ajustar os times')
        ->set('existingPlayerId', $player->id)
        ->call('addExistingPlayer')
        ->set('firstSwapParticipantId', $home->id)
        ->set('secondSwapParticipantId', $away->id)
        ->call('swapPlayers')
        ->assertHasNoErrors();

    expect($meeting->participants()->where('player_id', $player->id)->count())->toBe(1);
    expect($meeting->rosterChanges()->where('type', 'swap')->count())->toBe(1);
});
