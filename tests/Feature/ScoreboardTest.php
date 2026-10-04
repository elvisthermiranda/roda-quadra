<?php

use App\Models\Meeting;
use App\Models\User;
use App\Services\MatchService;
use App\Services\MeetingService;
use App\Services\SubstitutionService;
use App\Services\TeamFormationService;

test('the organizer can share a scoreboard link that guests can open', function () {
    $this->actingAs(User::factory()->create());
    $meeting = Meeting::factory()->create();
    $scoreboardUrl = route('scoreboard', ['token' => $meeting->display_token]);

    expect($meeting->display_token)->toHaveLength(40);
    $this->get('/painel')->assertOk()->assertSee($scoreboardUrl);

    $this->post('/logout')->assertRedirect(route('home'));
    $this->get($scoreboardUrl)
        ->assertOk()
        ->assertSee('A primeira partida vem aí.')
        ->assertSee('Próximo time em formação')
        ->assertSee('wire:poll.5s.keep-alive')
        ->assertSee('noindex, nofollow');
});

test('the shared scoreboard shows live teams scores and the first waiting team', function () {
    $this->actingAs(User::factory()->create());
    $meeting = Meeting::factory()->create();
    $formation = app(TeamFormationService::class);

    for ($number = 1; $number <= 12; $number++) {
        $formation->arrive($meeting, 'Pessoa '.$number, 'female', 3);
    }

    $match = $meeting->matches()->firstOrFail();
    $matches = app(MatchService::class);
    $matches->changeScore($match, 'home', 1);
    $matches->changeScore($match, 'away', 1);
    $matches->changeScore($match, 'home', 1);
    $url = route('scoreboard', ['token' => $meeting->display_token]);

    $this->post('/logout');
    $this->get($url)
        ->assertOk()
        ->assertSee('Em jogo agora')
        ->assertSee('Time 1')
        ->assertSee('Time 2')
        ->assertSee('Time 3')
        ->assertSee('Placar: Time 1 2, Time 2 1')
        ->assertSee('Pessoa 9')
        ->assertSee('Pessoa 12');

    $matches->changeScore($match, 'away', 1);
    $this->get($url)->assertOk()->assertSee('Placar: Time 1 2, Time 2 2');

    $departing = $meeting->participants()->with('player')->where('team_id', $match->home_team_id)->firstOrFail();
    app(SubstitutionService::class)->depart($meeting, $departing->id);
    $this->get($url)->assertOk()->assertDontSee('>'.$departing->player->name.'</span>', false);
});

test('a finished meeting keeps the last score visible without a next team', function () {
    $this->actingAs(User::factory()->create());
    $meeting = Meeting::factory()->create(['sport' => 'futsal']);
    $formation = app(TeamFormationService::class);

    for ($number = 1; $number <= 8; $number++) {
        $formation->arrive($meeting, 'Pessoa '.$number, 'male', 3);
    }

    $match = $meeting->matches()->firstOrFail();
    $match->update(['home_score' => 2, 'away_score' => 1]);
    app(MatchService::class)->confirm($match);
    app(MeetingService::class)->close($meeting);

    $this->post('/logout');
    $this->get(route('scoreboard', ['token' => $meeting->display_token]))
        ->assertOk()
        ->assertSee('Último resultado')
        ->assertSee('Encontro encerrado')
        ->assertDontSee('Próximo time em formação');
});

test('the scoreboard follows the next match after a result changes the queue', function () {
    $this->actingAs(User::factory()->create());
    $meeting = Meeting::factory()->create();
    $formation = app(TeamFormationService::class);

    for ($number = 1; $number <= 12; $number++) {
        $formation->arrive($meeting, 'Jogador '.$number, 'female', 3);
    }

    $firstMatch = $meeting->matches()->firstOrFail();
    $firstMatch->update(['home_score' => 15]);
    app(MatchService::class)->confirm($firstMatch);

    $this->post('/logout');
    $this->get(route('scoreboard', ['token' => $meeting->display_token]))
        ->assertOk()
        ->assertSee('Partida 2')
        ->assertSee('Placar: Time 1 0, Time 3 0')
        ->assertSee('Na sequência')
        ->assertSee('Time 2');
});

test('waiting players are visible while the next team is still being formed', function () {
    $this->actingAs(User::factory()->create());
    $meeting = Meeting::factory()->create();
    $formation = app(TeamFormationService::class);

    for ($number = 1; $number <= 11; $number++) {
        $formation->arrive($meeting, 'Pessoa '.$number, 'female', 3);
    }

    $this->post('/logout');
    $this->get(route('scoreboard', ['token' => $meeting->display_token]))
        ->assertOk()
        ->assertSee('Próximo time em formação')
        ->assertSee('3 pessoas aguardando')
        ->assertSee('Pessoa 9')
        ->assertSee('Pessoa 10')
        ->assertSee('Pessoa 11');
});

test('a guessed meeting id or unknown display token cannot open a scoreboard', function () {
    $meeting = Meeting::factory()->create();

    $this->get('/placar/'.$meeting->id)->assertNotFound();
    $this->get('/placar/'.str_repeat('a', 40))->assertNotFound();
});

test('a completed meeting without matches has an accurate empty state', function () {
    $meeting = Meeting::factory()->create(['status' => 'completed']);

    $this->get(route('scoreboard', ['token' => $meeting->display_token]))
        ->assertOk()
        ->assertSee('Nenhuma partida foi registrada.')
        ->assertDontSee('A primeira partida vem aí.');
});

test('a shared scoreboard contains only the meeting named by its token', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $firstMeeting = Meeting::factory()->create(['user_id' => $firstUser->id]);
    $secondMeeting = Meeting::factory()->create(['user_id' => $secondUser->id]);
    $formation = app(TeamFormationService::class);

    for ($number = 1; $number <= 8; $number++) {
        $formation->arrive($firstMeeting, 'Grupo A '.$number, 'female', 3);
        $formation->arrive($secondMeeting, 'Grupo B '.$number, 'male', 3);
    }

    $this->get(route('scoreboard', ['token' => $firstMeeting->display_token]))
        ->assertOk()
        ->assertSee('Grupo A 1')
        ->assertDontSee('Grupo B 1');

    $this->actingAs($secondUser);
    $this->get('/painel')->assertOk()->assertSee($secondMeeting->display_token)->assertDontSee($firstMeeting->display_token);
});
