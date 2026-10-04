<?php

use App\Models\Meeting;
use App\Models\User;
use App\Services\MatchService;
use App\Services\MeetingService;
use App\Services\TeamFormationService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('football starts an eleven player match and records goals without a point target', function () {
    Livewire::test('pages::dashboard')
        ->call('chooseSport', 'football')
        ->assertSet('teamSize', 11)
        ->call('startMeeting')
        ->assertHasNoErrors()
        ->assertSee('Futebol · Formato 11×11');

    $meeting = Meeting::query()->firstOrFail();
    $formation = app(TeamFormationService::class);

    for ($number = 1; $number <= 22; $number++) {
        $formation->arrive($meeting, 'Jogador '.$number, $number % 2 === 0 ? 'female' : 'male', 3);
    }

    $match = $meeting->matches()->firstOrFail();
    $match->update(['target_score' => 1]);
    $matches = app(MatchService::class);

    expect($meeting->sport)->toBe('football');
    expect($match->home_roster)->toHaveCount(11);
    expect($match->away_roster)->toHaveCount(11);

    for ($goal = 0; $goal < 3; $goal++) {
        $matches->changeScore($match, 'home', 1);
    }

    $matches->changeScore($match, 'away', 1);
    $matches->changeScore($match, 'away', 1);
    expect(fn () => $matches->confirm($match, false, 'away'))->toThrow(ValidationException::class);
    $matches->confirm($match);

    expect($match->fresh()->winner_team_id)->toBe($match->home_team_id);
    $this->get('/painel')->assertOk()->assertSee('Saldo de gols')->assertDontSee('Pontos por partida');
});

test('a futsal draw asks for a tiebreak winner before advancing the queue', function () {
    $meeting = app(MeetingService::class)->create(auth()->user(), 5, 'futsal');
    $formation = app(TeamFormationService::class);

    for ($number = 1; $number <= 10; $number++) {
        $formation->arrive($meeting, 'Jogador '.$number, 'male', 3);
    }

    $match = $meeting->matches()->firstOrFail();
    $matches = app(MatchService::class);
    $matches->changeScore($match, 'home', 1);
    $matches->changeScore($match, 'away', 1);

    expect($matches->winner($match->fresh()))->toBeNull();
    expect(fn () => $matches->confirm($match))->toThrow(ValidationException::class);
    expect(fn () => $matches->confirm($match, false, 'invalid'))->toThrow(ValidationException::class);

    Livewire::test('pages::dashboard')
        ->assertSee('Placar empatado')
        ->call('confirmDrawWinner', 'away', false)
        ->assertHasNoErrors()
        ->assertSee('Desempate: Time 2 venceu');

    expect($match->fresh()->winner_team_id)->toBe($match->away_team_id);
    expect($match->fresh()->home_score)->toBe(1);
    expect($match->fresh()->away_score)->toBe(1);
});

test('invalid sports and unsupported team sizes cannot create meetings', function () {
    $meetings = app(MeetingService::class);

    expect(fn () => $meetings->create(auth()->user(), 11, 'basketball'))->toThrow(ValidationException::class);
    expect(fn () => $meetings->create(auth()->user(), 12, 'football'))->toThrow(ValidationException::class);
    expect(Meeting::query()->count())->toBe(0);
});
