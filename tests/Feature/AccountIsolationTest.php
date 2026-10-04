<?php

use App\Models\Meeting;
use App\Models\Player;
use App\Models\User;
use App\Services\MeetingService;
use App\Services\TeamFormationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('guests can see the home page but sign in before opening the dashboard', function () {
    $this->get('/')->assertOk()->assertSee('RodaQuadra')->assertSee(route('register'));
    $this->get('/painel')->assertRedirect(route('login'));
    $this->get('/login')->assertOk()->assertSee('Seu jogo, sua fila.');
    $this->get('/register')->assertOk()->assertSee('Organize seu grupo.');
});

test('signed in organizers can open the dashboard from the home page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertOk()->assertSee('Abrir painel')->assertSee(route('dashboard'));
    $this->get('/painel')->assertOk()->assertSee('RodaQuadra');
});

test('organizers return to the public home page after signing out', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/logout')->assertRedirect(route('home'));
    $this->assertGuest();
});

test('an organizer can create an account and sign in', function () {
    $this->post('/register', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'senha-segura-123',
        'password_confirmation' => 'senha-segura-123',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    expect(User::query()->where('email', 'ana@example.com')->exists())->toBeTrue();
});

test('a wrong password cannot open another organizers dashboard', function () {
    User::factory()->create(['email' => 'ana@example.com']);

    $this->post('/login', ['email' => 'ana@example.com', 'password' => 'senha-errada'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('organizers see only their own meetings and players', function () {
    $first = User::factory()->create(['name' => 'Grupo A']);
    $second = User::factory()->create(['name' => 'Grupo B']);
    $foreignMeeting = Meeting::factory()->create(['user_id' => $first->id]);
    $foreignPlayer = Player::factory()->create(['user_id' => $first->id, 'name' => 'Jogador privado']);
    $this->actingAs($second);

    $this->get('/painel')->assertOk()->assertDontSee('Jogador privado');
    Livewire::test('pages::dashboard')
        ->assertSet('meetingId', null)
        ->assertDontSee('Jogador privado')
        ->call('startMeeting')
        ->assertHasNoErrors();

    expect($second->meetings()->count())->toBe(1);
    expect($foreignMeeting->fresh()->user_id)->toBe($first->id);
    expect(fn () => app(TeamFormationService::class)->arriveExisting($second->meetings()->firstOrFail(), $foreignPlayer->id))
        ->toThrow(ModelNotFoundException::class);
});

test('another organizers meeting cannot be selected or changed by id', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $foreignMeeting = Meeting::factory()->create(['user_id' => $first->id]);
    $this->actingAs($second);

    Livewire::test('pages::dashboard')->call('selectMeeting', $foreignMeeting->id)->assertNotFound();
    Livewire::test('pages::dashboard')->set('meetingId', $foreignMeeting->id)
        ->set('name', 'Invasor')->call('addPlayer')->assertNotFound();

    expect($foreignMeeting->participants()->count())->toBe(0);
});

test('each organizer may have an active meeting at the same time', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $meetings = app(MeetingService::class);

    expect($meetings->create($first, 4)->user_id)->toBe($first->id);
    expect($meetings->create($second, 6)->user_id)->toBe($second->id);
});

test('old unowned data can be linked only through an explicit console action', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => null]);
    $player = Player::factory()->create(['user_id' => null]);

    $this->artisan('meetings:claim-legacy', ['email' => $user->email])->assertSuccessful();

    expect($meeting->fresh()->user_id)->toBe($user->id);
    expect($player->fresh()->user_id)->toBe($user->id);
});
