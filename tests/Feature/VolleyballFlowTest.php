<?php

use App\Models\Meeting;
use App\Models\User;
use App\Services\MatchService;
use App\Services\MeetingService;
use App\Services\RankingService;
use App\Services\TeamFormationService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

function addPlayers(Meeting $meeting, int $count): void
{
    $formation = app(TeamFormationService::class);

    for ($index = 1; $index <= $count; $index++) {
        $formation->arrive($meeting, 'Pessoa '.$index, $index % 2 === 0 ? 'female' : 'male', 3);
    }
}

test('the first eight arrivals play and are balanced between the first teams', function () {
    $meeting = Meeting::factory()->create();

    addPlayers($meeting, 8);

    $teams = $meeting->teams()->with('participants.player')->orderBy('id')->get();
    $match = $meeting->matches()->firstOrFail();

    expect($teams)->toHaveCount(2);
    expect($match->home_roster)->toHaveCount(4);
    expect($match->away_roster)->toHaveCount(4);
    expect($teams[0]->participants->pluck('arrival_order')->merge($teams[1]->participants->pluck('arrival_order'))->sort()->values()->all())->toBe(range(1, 8));
    expect($teams[0]->participants->where('player.gender', 'female')->count())->toBe(2);
    expect($teams[1]->participants->where('player.gender', 'female')->count())->toBe(2);
});

test('a complete first team waits for four more arrivals before playing', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 4);

    expect($meeting->teams()->count())->toBe(1);
    expect($meeting->matches()->count())->toBe(0);
    expect($meeting->teams()->firstOrFail()->participants()->count())->toBe(4);
});

test('the winner stays once and leaves before the loser after two games when two teams wait', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 16);
    $matches = app(MatchService::class);
    $first = $meeting->matches()->firstOrFail();
    $winnerId = $first->home_team_id;

    for ($point = 0; $point < 15; $point++) {
        $matches->changeScore($first, 'home', 1);
    }

    $matches->confirm($first);
    $second = $meeting->matches()->where('status', 'playing')->firstOrFail();
    expect($second->home_team_id)->toBe($winnerId);

    for ($point = 0; $point < 15; $point++) {
        $matches->changeScore($second, 'home', 1);
    }

    $matches->confirm($second);
    $third = $meeting->matches()->where('status', 'playing')->firstOrFail();
    $queue = $meeting->teams()->where('status', 'waiting')->orderBy('queue_position')->get();

    expect([$third->home_team_id, $third->away_team_id])->not->toContain($winnerId);
    expect($queue->pluck('id')->all())->toBe([$winnerId, $second->away_team_id]);
    expect($queue->pluck('consecutive_games')->all())->toBe([0, 0]);
});

test('a match tied at fourteen ends when one side reaches seventeen', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 8);
    $matches = app(MatchService::class);
    $match = $meeting->matches()->firstOrFail();

    for ($point = 0; $point < 14; $point++) {
        $matches->changeScore($match, 'home', 1);
        $matches->changeScore($match, 'away', 1);
    }

    for ($point = 0; $point < 3; $point++) {
        $matches->changeScore($match, 'home', 1);
    }

    expect($matches->winner($match->fresh()))->id->toBe($match->home_team_id);
    $matches->confirm($match);
    expect($match->fresh()->status)->toBe('confirmed');
});

test('a changed point target applies to the next match and keeps the current target', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 8);
    $first = $meeting->matches()->firstOrFail();
    app(MeetingService::class)->updateSettings($meeting, 100, 1, 'win_rate', 11);

    expect($first->fresh()->target_score)->toBe(15);
    $first->update(['home_score' => 15]);
    $matches = app(MatchService::class);
    $matches->confirm($first);

    $second = $meeting->matches()->where('status', 'playing')->firstOrFail();
    expect($second->target_score)->toBe(11);

    $second->update(['home_score' => 10, 'away_score' => 10]);
    expect($matches->winner($second->fresh()))->toBeNull();
    $second->update(['home_score' => 13]);
    expect($matches->winner($second->fresh())?->id)->toBe($second->home_team_id);
});

test('a match timer can be paused and its duration stays with the result', function () {
    $meeting = Meeting::factory()->create(['duration_minutes' => 10]);
    addPlayers($meeting, 8);
    $match = $meeting->matches()->firstOrFail();
    $matches = app(MatchService::class);

    $this->get('/painel')->assertOk()->assertSee('Tempo restante');

    $matches->startTimer($match);
    $this->travel(75)->seconds();
    $matches->pauseTimer($match);
    expect($match->fresh()->timer_elapsed_seconds)->toBe(75);

    $matches->startTimer($match);
    $this->travel(25)->seconds();
    $match->update(['home_score' => 15]);
    $matches->confirm($match);

    expect($match->fresh()->timer_elapsed_seconds)->toBe(100);
    expect($match->fresh()->timer_started_at)->toBeNull();
    expect($match->fresh()->duration_minutes)->toBe(10);
});

test('the timer length changes only for future matches', function () {
    $meeting = Meeting::factory()->create(['duration_minutes' => 10]);
    addPlayers($meeting, 8);
    $first = $meeting->matches()->firstOrFail();
    app(MeetingService::class)->updateSettings($meeting, 100, 1, 'win_rate', 15, 5);

    expect($first->fresh()->duration_minutes)->toBe(10);
    $first->update(['home_score' => 15]);
    app(MatchService::class)->confirm($first);

    expect($meeting->matches()->where('status', 'playing')->firstOrFail()->duration_minutes)->toBe(5);
});

test('a winner may stay for a third game when fewer than two teams wait', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 12);
    $matches = app(MatchService::class);

    for ($game = 0; $game < 2; $game++) {
        $match = $meeting->matches()->where('status', 'playing')->firstOrFail();

        for ($point = 0; $point < 15; $point++) {
            $matches->changeScore($match, 'home', 1);
        }

        $matches->confirm($match);
    }

    $current = $meeting->matches()->where('status', 'playing')->firstOrFail();
    expect($current->home_team_id)->toBe($meeting->matches()->where('sequence', 1)->firstOrFail()->home_team_id);
    expect($meeting->teams()->findOrFail($current->home_team_id)->consecutive_games)->toBe(2);
});

test('undo restores the exact queue and consecutive counters', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 12);
    $matches = app(MatchService::class);
    $first = $meeting->matches()->firstOrFail();
    $before = $meeting->teams()->orderBy('id')->get()->map->only(['status', 'queue_position', 'consecutive_games'])->all();

    for ($point = 0; $point < 15; $point++) {
        $matches->changeScore($first, 'home', 1);
    }

    $matches->confirm($first);
    $matches->undo($meeting);

    expect($meeting->matches()->count())->toBe(1);
    expect($first->fresh()->status)->toBe('playing');
    expect($meeting->teams()->orderBy('id')->get()->map->only(['status', 'queue_position', 'consecutive_games'])->all())->toBe($before);
});

test('waiting arrivals play next with players from the losing team', function (int $playerCount, int $borrowedCount) {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, $playerCount);
    $first = $meeting->matches()->firstOrFail();
    $first->update(['home_score' => 15]);

    app(MatchService::class)->confirm($first);

    $next = $meeting->matches()->where('status', 'playing')->firstOrFail();
    $newTeam = $next->awayTeam;
    $arrivalOrders = $newTeam->participants()->where('status', 'present')->orderBy('arrival_order')->pluck('arrival_order')->all();

    expect($next->home_team_id)->toBe($first->home_team_id);
    expect($newTeam->id)->not->toBe($first->away_team_id);
    expect($arrivalOrders)->toContain(...range(9, $playerCount));
    expect($arrivalOrders)->toHaveCount(4);
    expect($meeting->teams()->findOrFail($first->away_team_id)->participants()->where('status', 'present')->count())->toBe(4 - $borrowedCount);
    expect($meeting->rosterChanges()->where('match_id', $next->id)->where('type', 'rotation')->count())->toBe($borrowedCount);
    expect($next->away_roster)->toHaveCount(4);
})->with([[9, 3], [10, 2], [11, 1]]);

test('a second result gives the remaining losing players the next turn', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 11);
    $matches = app(MatchService::class);
    $first = $meeting->matches()->firstOrFail();
    $firstRoster = $first->away_roster;
    $first->update(['home_score' => 15]);
    $matches->confirm($first);

    $second = $meeting->matches()->where('status', 'playing')->firstOrFail();
    $firstBorrowerId = $meeting->rosterChanges()->where('match_id', $second->id)->where('type', 'rotation')->firstOrFail()->incoming_participant_id;
    $second->update(['home_score' => 15]);
    $matches->confirm($second);

    $third = $meeting->matches()->where('status', 'playing')->firstOrFail();

    expect($third->away_team_id)->toBe($first->away_team_id);
    expect($third->away_roster)->toHaveCount(4);
    expect($meeting->teams()->findOrFail($second->away_team_id)->participants()->where('status', 'present')->count())->toBe(3);
    expect($meeting->participants()->findOrFail($firstBorrowerId)->team_id)->toBe($second->away_team_id);
    expect($meeting->rosterChanges()->where('match_id', $third->id)->where('type', 'rotation')->firstOrFail()->incoming_participant_id)->not->toBe($firstBorrowerId);
    expect($first->fresh()->away_roster)->toBe($firstRoster);
});

test('waiting arrivals also enter when the away team wins', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 11);
    $first = $meeting->matches()->firstOrFail();
    $first->update(['away_score' => 15]);

    app(MatchService::class)->confirm($first);

    $next = $meeting->matches()->where('status', 'playing')->firstOrFail();

    expect($next->home_team_id)->toBe($first->away_team_id);
    expect($next->away_team_id)->not->toBe($first->home_team_id);
    expect($meeting->teams()->findOrFail($first->home_team_id)->participants()->where('status', 'present')->count())->toBe(3);
});

test('fixed teams keep their roster until the organizer changes it', function () {
    $meeting = Meeting::factory()->create(['team_mode' => 'fixed']);
    addPlayers($meeting, 11);
    $first = $meeting->matches()->firstOrFail();
    $first->update(['home_score' => 15]);

    app(MatchService::class)->confirm($first);

    $next = $meeting->matches()->where('status', 'playing')->firstOrFail();

    expect($next->home_team_id)->toBe($first->home_team_id);
    expect($next->away_team_id)->toBe($first->away_team_id);
    expect($meeting->teams()->count())->toBe(2);
    expect($meeting->participants()->whereNull('team_id')->count())->toBe(3);
    expect($meeting->rosterChanges()->count())->toBe(0);
});

test('undo returns borrowed players and late arrivals to their previous positions', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 11);
    $first = $meeting->matches()->firstOrFail();
    $first->update(['home_score' => 15]);
    $before = $meeting->teams()->orderBy('id')->get()->map->only(['status', 'queue_position', 'consecutive_games'])->all();

    $matches = app(MatchService::class);
    $matches->confirm($first);
    expect($meeting->teams()->count())->toBe(3);
    $matches->undo($meeting);

    expect($meeting->teams()->count())->toBe(2);
    expect($meeting->matches()->count())->toBe(1);
    expect($meeting->participants()->whereIn('arrival_order', [9, 10, 11])->whereNull('team_id')->count())->toBe(3);
    expect($meeting->teams()->findOrFail($first->away_team_id)->participants()->where('status', 'present')->count())->toBe(4);
    expect($meeting->teams()->orderBy('id')->get()->map->only(['status', 'queue_position', 'consecutive_games'])->all())->toBe($before);
    expect($meeting->rosterChanges()->count())->toBe(0);
});

test('an already started queue can place three waiting people in the next unscored match', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 11);
    $first = $meeting->matches()->firstOrFail();
    $first->update([
        'status' => 'confirmed',
        'home_score' => 15,
        'winner_team_id' => $first->home_team_id,
        'queue_snapshot' => $meeting->teams()->orderBy('id')->get()->map->only(['id', 'status', 'queue_position', 'consecutive_games'])->all(),
    ]);
    $current = $meeting->matches()->create([
        'sequence' => 2,
        'home_team_id' => $first->home_team_id,
        'away_team_id' => $first->away_team_id,
        'home_roster' => $first->home_roster,
        'away_roster' => $first->away_roster,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Colocar 3 pessoas aguardando em quadra')
        ->call('promoteWaitingPlayers')
        ->assertHasNoErrors()
        ->assertDontSee('Colocar 3 pessoas aguardando em quadra');

    expect($current->fresh()->home_team_id)->toBe($first->home_team_id);
    expect($current->fresh()->away_team_id)->not->toBe($first->away_team_id);
    expect($meeting->teams()->findOrFail($first->away_team_id)->status)->toBe('waiting');
    expect($meeting->teams()->count())->toBe(3);
    expect($meeting->matches()->where('status', 'confirmed')->count())->toBe(1);

    app(MatchService::class)->undo($meeting);

    expect($meeting->teams()->count())->toBe(2);
    expect($meeting->participants()->whereNull('team_id')->count())->toBe(3);
});

test('an unfinished result cannot advance the queue twice', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 8);
    $match = $meeting->matches()->firstOrFail();

    expect(fn () => app(MatchService::class)->confirm($match))->toThrow(ValidationException::class);
    expect($meeting->matches()->count())->toBe(1);
});

test('the same pair cannot start another match while a game is in progress', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 8);
    $match = $meeting->matches()->firstOrFail();

    expect(fn () => app(MatchService::class)->start($meeting, $match->homeTeam, $match->awayTeam))
        ->toThrow(ValidationException::class);
    expect($meeting->matches()->count())->toBe(1);
});

test('the dashboard shows the active meeting and its current score', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 8);

    $this->get('/painel')->assertOk()->assertSee('Em jogo')->assertSee('Partida 1')->assertSee('Equilíbrio dos times')->assertSee('Pessoa 1');
});

test('ranking uses confirmed results and keeps scored and conceded points', function () {
    $meeting = Meeting::factory()->create();
    addPlayers($meeting, 8);
    $match = $meeting->matches()->firstOrFail();
    $match->update(['home_score' => 15, 'away_score' => 10]);
    app(MatchService::class)->confirm($match);

    $ranking = app(RankingService::class)->forMeeting($meeting);

    expect($ranking->first()['team_id'])->toBe($match->home_team_id);
    expect($ranking->first()['wins'])->toBe(1);
    expect($ranking->first()['points_for'])->toBe(15);
    expect($ranking->first()['points_against'])->toBe(10);
    expect($ranking->first()['point_balance'])->toBe(5);
});

test('the dashboard creates a meeting and records an arrival', function () {
    Livewire::test('pages::dashboard')
        ->set('teamSize', 4)
        ->call('startMeeting')
        ->set('name', 'Ana')
        ->set('gender', 'female')
        ->set('skillLevel', 4)
        ->call('addPlayer')
        ->assertHasNoErrors();

    expect(Meeting::count())->toBe(1);
    expect(Meeting::firstOrFail()->participants()->firstOrFail()->player->name)->toBe('Ana');
});
