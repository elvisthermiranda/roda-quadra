<?php

use App\Models\Meeting;
use App\Models\Team;
use App\Models\User;
use App\Models\VolleyballMatch;
use App\Services\MatchService;
use App\Services\MeetingService;
use App\Services\RankingService;
use App\Services\TeamFormationService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('meeting settings are saved and loaded when selecting another meeting', function () {
    $first = Meeting::factory()->create();
    $second = Meeting::factory()->create(['status' => 'completed']);

    Livewire::test('pages::dashboard')
        ->assertSet('meetingId', $first->id)
        ->set('genderWeight', 0)
        ->set('skillWeight', 5)
        ->set('rankingTiebreaker', 'point_balance')
        ->set('teamMode', 'fixed')
        ->call('saveSettings')
        ->assertHasNoErrors()
        ->call('selectMeeting', $second->id)
        ->assertSet('genderWeight', 100)
        ->call('selectMeeting', $first->id)
        ->assertSet('genderWeight', 0)
        ->assertSet('rankingTiebreaker', 'point_balance');

    expect($first->fresh()->skill_weight)->toBe(5);
    expect($first->fresh()->team_mode)->toBe('fixed');
});

test('meeting settings require at least one balance weight', function () {
    $meeting = Meeting::factory()->create();

    expect(fn () => app(MeetingService::class)->updateSettings($meeting, 0, 0, 'win_rate'))
        ->toThrow(ValidationException::class);

    Livewire::test('pages::dashboard')
        ->set('genderWeight', 0)
        ->set('skillWeight', 0)
        ->call('saveSettings')
        ->assertHasErrors(['settings']);
});

test('a new meeting starts after the active one is closed', function () {
    $first = Meeting::factory()->create();
    $meetings = app(MeetingService::class);

    expect(fn () => $meetings->create(auth()->user(), 4))->toThrow(ValidationException::class);

    $meetings->close($first);
    $second = $meetings->create(auth()->user(), 5);

    expect($second->team_size)->toBe(5);
    expect($second->status)->toBe('active');
    expect($second->gender_weight)->toBe(100);
});

test('an organizer can choose a custom ten player team size', function () {
    Livewire::test('pages::dashboard')
        ->set('teamSize', 10)
        ->call('startMeeting')
        ->assertHasNoErrors()
        ->assertSee('Formato 10×10');

    expect(Meeting::query()->firstOrFail()->team_size)->toBe(10);
});

test('a completed meeting can be followed by a custom ten player format', function () {
    Meeting::factory()->create(['status' => 'completed']);

    Livewire::test('pages::dashboard')
        ->set('teamSize', 10)
        ->call('startMeeting')
        ->assertHasNoErrors()
        ->assertSee('Formato 10×10');

    expect(Meeting::query()->where('status', 'active')->firstOrFail()->team_size)->toBe(10);
});

test('team sizes outside the supported range are rejected', function (int $teamSize) {
    Livewire::test('pages::dashboard')
        ->set('teamSize', $teamSize)
        ->call('startMeeting')
        ->assertHasErrors(['teamSize']);

    expect(Meeting::query()->count())->toBe(0);
    expect(fn () => app(MeetingService::class)->create(auth()->user(), $teamSize))
        ->toThrow(ValidationException::class);
})->with([1, 12]);

test('ranking uses the selected tie breaker after wins', function () {
    $meeting = Meeting::factory()->create();
    $first = Team::factory()->create(['meeting_id' => $meeting->id]);
    $second = Team::factory()->create(['meeting_id' => $meeting->id]);
    $third = Team::factory()->create(['meeting_id' => $meeting->id]);

    VolleyballMatch::factory()->create([
        'meeting_id' => $meeting->id,
        'home_team_id' => $first->id, 'away_team_id' => $third->id,
        'sequence' => 1, 'status' => 'confirmed', 'winner_team_id' => $first->id,
        'home_score' => 15, 'away_score' => 0,
    ]);
    VolleyballMatch::factory()->create([
        'meeting_id' => $meeting->id,
        'home_team_id' => $second->id, 'away_team_id' => $third->id,
        'sequence' => 2, 'status' => 'confirmed', 'winner_team_id' => $second->id,
        'home_score' => 15, 'away_score' => 14,
    ]);
    VolleyballMatch::factory()->create([
        'meeting_id' => $meeting->id,
        'home_team_id' => $third->id, 'away_team_id' => $first->id,
        'sequence' => 3, 'status' => 'confirmed', 'winner_team_id' => $third->id,
        'home_score' => 15, 'away_score' => 14,
    ]);

    expect(app(RankingService::class)->forMeeting($meeting)->first()['team_id'])->toBe($second->id);

    app(MeetingService::class)->updateSettings($meeting, 100, 1, 'point_balance');

    expect(app(RankingService::class)->forMeeting($meeting->fresh())->first()['team_id'])->toBe($first->id);
});

test('closing a meeting preserves confirmed results and blocks further changes', function () {
    $meeting = Meeting::factory()->create();
    $formation = app(TeamFormationService::class);

    for ($index = 1; $index <= 8; $index++) {
        $formation->arrive($meeting, 'Pessoa '.$index, $index % 2 === 0 ? 'female' : 'male', 3);
    }

    $first = $meeting->matches()->firstOrFail();
    $first->update(['home_score' => 15]);
    app(MatchService::class)->confirm($first);
    $current = $meeting->matches()->where('status', 'playing')->firstOrFail();

    $dashboard = Livewire::test('pages::dashboard')
        ->call('requestCloseMeeting')
        ->assertDispatched('ts-ui:dialog');

    expect($meeting->fresh()->status)->toBe('active');

    $dashboard->call('closeMeeting')->assertHasNoErrors()->assertSee('Encontro encerrado');

    expect($meeting->fresh()->status)->toBe('completed');
    expect($first->fresh()->status)->toBe('confirmed');
    expect($current->fresh()->status)->toBe('cancelled');
    expect(app(RankingService::class)->forMeeting($meeting)->first()['wins'])->toBe(1);
    expect(fn () => app(MatchService::class)->changeScore($current, 'home', 1))->toThrow(ValidationException::class);
    expect(fn () => app(MatchService::class)->undo($meeting))->toThrow(ValidationException::class);
    expect(fn () => app(MeetingService::class)->updateSettings($meeting, 5, 1, 'win_rate'))->toThrow(ValidationException::class);

    Livewire::test('pages::dashboard')
        ->assertSet('meetingId', $meeting->id)
        ->assertSee('Encontro encerrado')
        ->assertDontSee('Registrar chegada');
});

test('closing a meeting cancels an unfinished match even if its score was started', function () {
    $meeting = Meeting::factory()->create();
    $formation = app(TeamFormationService::class);

    for ($index = 1; $index <= 8; $index++) {
        $formation->arrive($meeting, 'Pessoa '.$index, 'female', 3);
    }

    $match = $meeting->matches()->firstOrFail();
    app(MatchService::class)->changeScore($match, 'home', 1);
    app(MatchService::class)->changeScore($match, 'home', -1);

    app(MeetingService::class)->close($meeting);

    expect($meeting->fresh()->status)->toBe('completed');
    expect($match->fresh()->status)->toBe('cancelled');
});
