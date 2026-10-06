<?php

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\Team;
use App\Models\VolleyballMatch;
use App\Services\MatchService;
use App\Services\MeetingService;
use App\Services\RankingService;
use App\Services\SubstitutionService;
use App\Services\TeamAdjustmentService;
use App\Services\TeamBalanceService;
use App\Services\TeamFormationService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new #[Layout('layout')] class extends Component
{
    use Interactions;

    public ?int $meetingId = null;

    public int|string $teamSize = 4;

    public string $sport = 'volleyball';

    public int $genderWeight = 100;

    public int $skillWeight = 1;

    public string $rankingTiebreaker = 'win_rate';

    public int $targetScore = 15;

    public string $durationMinutes = '';

    public string $teamMode = 'dynamic';

    public string $name = '';

    public string $gender = 'female';

    public int $skillLevel = 3;

    public ?int $existingPlayerId = null;

    public ?int $firstSwapParticipantId = null;

    public ?int $secondSwapParticipantId = null;

    public function mount(): void
    {
        $this->meetingId = auth()->user()->meetings()->where('status', 'active')->latest('id')->value('id')
            ?? auth()->user()->meetings()->latest('id')->value('id');
        $this->loadSettings();
    }

    public function startMeeting(MeetingService $meetings): void
    {
        $this->validate([
            'sport' => ['required', 'in:volleyball,futsal,football'],
            'teamSize' => ['required', 'integer', 'between:2,11'],
        ]);
        $meeting = $meetings->create(auth()->user(), (int) $this->teamSize, $this->sport);
        $this->meetingId = $meeting->id;
        $this->loadSettings();
    }

    public function chooseSport(string $sport): void
    {
        if (! in_array($sport, ['volleyball', 'futsal', 'football'], true)) {
            return;
        }

        $this->sport = $sport;
        $this->teamSize = match ($sport) {
            'futsal' => 5,
            'football' => 11,
            default => 4,
        };
    }

    public function selectMeeting(int $meetingId): void
    {
        $this->meetingId = auth()->user()->meetings()->findOrFail($meetingId)->id;
        $this->loadSettings();
    }

    public function saveSettings(MeetingService $meetings): void
    {
        $this->validate([
            'genderWeight' => ['required', 'integer', 'between:0,100'],
            'skillWeight' => ['required', 'integer', 'between:0,100'],
            'rankingTiebreaker' => ['required', 'in:win_rate,point_balance'],
            'targetScore' => ['required', 'integer', 'between:5,30'],
            'durationMinutes' => ['nullable', 'integer', 'between:1,120'],
            'teamMode' => ['required', 'in:fixed,dynamic'],
        ]);

        $meetings->updateSettings($this->activeMeeting(), $this->genderWeight, $this->skillWeight, $this->rankingTiebreaker, $this->targetScore, $this->durationMinutes === '' ? null : (int) $this->durationMinutes, $this->teamMode);
        unset($this->meeting, $this->ranking);
    }

    public function closeMeeting(MeetingService $meetings): void
    {
        $meetings->close($this->activeMeeting());
        unset($this->meeting, $this->currentMatch);
    }

    public function requestCloseMeeting(): void
    {
        $this->dialog()
            ->question('Encerrar encontro?', 'A partida atual será cancelada, mas os resultados confirmados permanecerão no histórico.')
            ->confirm('Encerrar encontro', 'closeMeeting')
            ->cancel('Cancelar')
            ->send();
    }

    public function addPlayer(TeamFormationService $formation): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'gender' => ['required', 'in:male,female'],
            'skillLevel' => ['required', 'integer', 'between:1,5'],
        ]);

        $formation->arrive($this->activeMeeting(), $this->name, $this->gender, $this->skillLevel);
        $this->reset('name');
    }

    public function addExistingPlayer(TeamFormationService $formation): void
    {
        $this->validate(['existingPlayerId' => ['required', 'integer', Rule::exists('players', 'id')->where('user_id', auth()->id())]]);
        $formation->arriveExisting($this->activeMeeting(), $this->existingPlayerId);
        $this->reset('existingPlayerId');
    }

    public function swapPlayers(TeamAdjustmentService $adjustments): void
    {
        $this->validate([
            'firstSwapParticipantId' => ['required', 'integer'],
            'secondSwapParticipantId' => ['required', 'integer', 'different:firstSwapParticipantId'],
        ]);

        $adjustments->swap($this->activeMeeting(), $this->firstSwapParticipantId, $this->secondSwapParticipantId);
        $this->reset('firstSwapParticipantId', 'secondSwapParticipantId');
    }

    public function score(string $side, int $change, MatchService $matches): void
    {
        $matches->changeScore($this->currentMatchOrFail(), $side, $change);
    }

    public function startTimer(MatchService $matches): void
    {
        $matches->startTimer($this->currentMatchOrFail());
        unset($this->currentMatch);
    }

    public function pauseTimer(MatchService $matches): void
    {
        $matches->pauseTimer($this->currentMatchOrFail());
        unset($this->currentMatch);
    }

    public function resetTimer(MatchService $matches): void
    {
        $matches->resetTimer($this->currentMatchOrFail());
        unset($this->currentMatch);
    }

    public function confirmResult(MatchService $matches): void
    {
        $matches->confirm($this->currentMatchOrFail());
    }

    public function requestConfirmResult(): void
    {
        $this->dialog()
            ->question('Confirmar resultado?', 'O resultado será salvo e a fila avançará para a próxima partida.')
            ->confirm('Confirmar resultado', 'confirmResult')
            ->cancel('Cancelar')
            ->send();
    }

    public function confirmIncompleteResult(MatchService $matches): void
    {
        $matches->confirm($this->currentMatchOrFail(), true);
    }

    public function requestConfirmIncompleteResult(): void
    {
        $this->dialog()
            ->question('Autorizar jogo incompleto?', 'O resultado será confirmado e o próximo jogo poderá começar com uma equipe incompleta.')
            ->confirm('Autorizar e avançar', 'confirmIncompleteResult')
            ->cancel('Cancelar')
            ->send();
    }

    public function confirmDrawWinner(string $side, bool $allowIncompleteNext, MatchService $matches): void
    {
        $matches->confirm($this->currentMatchOrFail(), $allowIncompleteNext, $side);
    }

    public function requestConfirmDrawWinner(string $side, bool $allowIncompleteNext): void
    {
        $team = $side === 'home'
            ? $this->currentMatchOrFail()->homeTeam
            : $this->currentMatchOrFail()->awayTeam;
        $description = $allowIncompleteNext
            ? 'O desempate será confirmado e o próximo jogo poderá começar com uma equipe incompleta.'
            : 'O desempate será confirmado e a fila avançará para a próxima partida.';

        $this->dialog()
            ->question("Confirmar vitória de {$team->name}?", $description)
            ->confirm('Confirmar vencedor', 'confirmDrawWinner', [$side, $allowIncompleteNext])
            ->cancel('Cancelar')
            ->send();
    }

    public function departPlayer(int $participantId, SubstitutionService $substitutions): void
    {
        $substitutions->depart($this->activeMeeting(), $participantId);
    }

    public function requestDepartPlayer(int $participantId): void
    {
        $participant = $this->activeMeeting()->participants()->with('player')->findOrFail($participantId);

        $this->dialog()
            ->question('Registrar saída?', "Marcar {$participant->player->name} como pessoa que foi embora?")
            ->confirm('Registrar saída', 'departPlayer', $participantId)
            ->cancel('Cancelar')
            ->send();
    }

    public function loanPlayer(int $targetTeamId, int $participantId, SubstitutionService $substitutions): void
    {
        $substitutions->loan($this->activeMeeting(), $targetTeamId, $participantId);
    }

    public function requestLoanPlayer(int $targetTeamId, int $participantId): void
    {
        $this->dialog()
            ->question('Confirmar empréstimo?', 'Este empréstimo deixará o time de origem incompleto.')
            ->confirm('Confirmar empréstimo', 'loanPlayer', [$targetTeamId, $participantId])
            ->cancel('Cancelar')
            ->send();
    }

    public function undoResult(MatchService $matches): void
    {
        $matches->undo($this->activeMeeting());
    }

    public function requestUndoResult(): void
    {
        $this->dialog()
            ->question('Desfazer último resultado?', 'O placar, as escalações e a posição dos times na fila serão restaurados.')
            ->confirm('Desfazer resultado', 'undoResult')
            ->cancel('Cancelar')
            ->send();
    }

    public function promoteWaitingPlayers(MatchService $matches): void
    {
        $matches->promoteWaitingPlayers($this->activeMeeting());
        unset($this->currentMatch, $this->teams, $this->participants, $this->changes);
    }

    #[Computed]
    public function meeting(): ?Meeting
    {
        return $this->meetingId ? auth()->user()->meetings()->find($this->meetingId) : null;
    }

    #[Computed]
    public function meetings(): Collection
    {
        return auth()->user()->meetings()->latest('id')->limit(20)->get();
    }

    #[Computed]
    public function participants(): Collection
    {
        return $this->meeting?->participants()->with(['player', 'team'])->orderBy('arrival_order')->get() ?? collect();
    }

    #[Computed]
    public function teams(): Collection
    {
        return $this->meeting?->teams()->with(['participants' => fn ($query) => $query->where('status', 'present')->with('player')->orderBy('arrival_order')])->orderBy('id')->get() ?? collect();
    }

    #[Computed]
    public function currentMatch(): ?VolleyballMatch
    {
        return $this->meeting?->matches()->with(['homeTeam', 'awayTeam'])->where('status', 'playing')->first();
    }

    #[Computed]
    public function history(): Collection
    {
        return $this->meeting?->matches()->with(['homeTeam', 'awayTeam'])->where('status', 'confirmed')->orderByDesc('sequence')->get() ?? collect();
    }

    #[Computed]
    public function ranking(): Collection
    {
        return $this->meeting ? (new RankingService)->forMeeting($this->meeting) : collect();
    }

    #[Computed]
    public function vacancies(): Collection
    {
        if (! $this->meeting) {
            return collect();
        }

        $substitutions = new SubstitutionService;

        return $this->teams->filter(fn (Team $team): bool => $team->participants->count() < $this->meeting->team_size)
            ->map(function (Team $team) use ($substitutions): array {
                $suggestion = $substitutions->suggestLoan($this->meeting, $team);
                $source = $suggestion ? $this->teams->firstWhere('id', $suggestion->team_id) : null;
                $isWoman = $suggestion?->player->gender === 'female' ? 1 : 0;

                return [
                    'team' => $team,
                    'suggestion' => $suggestion,
                    'impact' => $suggestion && $source ? [
                        'target_women' => $team->participants->where('player.gender', 'female')->count() + $isWoman,
                        'target_skill' => (int) $team->participants->sum('player.skill_level') + $suggestion->player->skill_level,
                        'source_women' => $source->participants->where('player.gender', 'female')->count() - $isWoman,
                        'source_skill' => (int) $source->participants->sum('player.skill_level') - $suggestion->player->skill_level,
                    ] : null,
                ];
            })
            ->values();
    }

    #[Computed]
    public function changes(): Collection
    {
        return $this->meeting?->rosterChanges()->with(['outgoingParticipant.player', 'incomingParticipant.player', 'team', 'sourceTeam'])->orderByDesc('id')->get() ?? collect();
    }

    #[Computed]
    public function availablePlayers(): Collection
    {
        if (! $this->meeting) {
            return collect();
        }

        $meetingId = $this->meeting->id;

        return auth()->user()->players()
            ->whereDoesntHave('participants', fn ($query) => $query->where('meeting_id', $meetingId)->where('status', 'present'))
            ->with(['participants' => fn ($query) => $query->where('meeting_id', $meetingId)])
            ->orderBy('name')->orderBy('id')->get();
    }

    #[Computed]
    public function canSwap(): bool
    {
        $match = $this->currentMatch;

        return $this->meeting?->status === 'active' && $match !== null && $match->score_started_at === null && $match->home_score === 0 && $match->away_score === 0
            && ! $this->changes->contains(fn ($change): bool => $change->match_id === $match->id && $change->type !== 'swap');
    }

    #[Computed]
    public function canPromoteWaitingPlayers(): bool
    {
        $current = $this->currentMatch;
        $previous = $this->history->first();
        $unassignedCount = $this->participants->where('status', 'present')->whereNull('team_id')->count();

        return $this->meeting?->status === 'active' && $this->meeting->team_mode === 'dynamic' && $current !== null && $previous !== null
            && $current->sequence === $previous->sequence + 1 && $current->home_team_id === $previous->winner_team_id
            && $current->away_team_id === ($previous->home_team_id === $previous->winner_team_id ? $previous->away_team_id : $previous->home_team_id)
            && $current->score_started_at === null && $current->home_score === 0 && $current->away_score === 0
            && $unassignedCount > 0 && $unassignedCount < $this->meeting->team_size
            && $this->teams->where('status', 'waiting')->isEmpty()
            && ! $this->changes->contains(fn ($change): bool => $change->match_id === $current->id);
    }

    #[Computed]
    public function homePlayers(): Collection
    {
        return $this->currentMatch ? ($this->teams->firstWhere('id', $this->currentMatch->home_team_id)?->participants ?? collect()) : collect();
    }

    #[Computed]
    public function awayPlayers(): Collection
    {
        return $this->currentMatch ? ($this->teams->firstWhere('id', $this->currentMatch->away_team_id)?->participants ?? collect()) : collect();
    }

    #[Computed]
    public function balanceSummary(): ?array
    {
        $match = $this->currentMatch;

        return $match ? (new TeamBalanceService)->describe($match->homeTeam, $match->awayTeam) : null;
    }

    private function activeMeeting(): Meeting
    {
        return auth()->user()->meetings()->where('status', 'active')->findOrFail($this->meetingId);
    }

    private function loadSettings(): void
    {
        $meeting = $this->meetingId ? auth()->user()->meetings()->find($this->meetingId) : null;
        $this->sport = $meeting?->sport ?? 'volleyball';
        $this->teamSize = $meeting?->team_size ?? 4;
        $this->genderWeight = $meeting?->gender_weight ?? 100;
        $this->skillWeight = $meeting?->skill_weight ?? 1;
        $this->rankingTiebreaker = $meeting?->ranking_tiebreaker ?? 'win_rate';
        $this->targetScore = $meeting?->target_score ?? 15;
        $this->durationMinutes = $meeting?->duration_minutes === null ? '' : (string) $meeting->duration_minutes;
        $this->teamMode = $meeting?->team_mode ?? 'dynamic';
    }

    private function currentMatchOrFail(): VolleyballMatch
    {
        return $this->activeMeeting()->matches()->where('status', 'playing')->firstOrFail();
    }
};
?>

<div class="mx-auto flex min-h-screen max-w-7xl flex-col gap-8 px-4 py-6 sm:px-6 lg:px-8">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-6">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-emerald-400">Organização de partidas</p>
            <h1 class="mt-1 text-3xl font-black tracking-tight sm:text-4xl">RodaQuadra</h1>
        </div>
        @if ($this->meetings->isNotEmpty())
            <label class="flex items-center gap-2 text-sm text-slate-300">
                Encontro
                <select wire:change="selectMeeting($event.target.value)" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                    @foreach ($this->meetings as $listedMeeting)
                        <option value="{{ $listedMeeting->id }}" @selected($listedMeeting->id === $meetingId)>
                            #{{ $listedMeeting->id }} · {{ $listedMeeting->played_on->format('d/m/Y') }} · {{ match ($listedMeeting->sport) { 'futsal' => 'Futsal', 'football' => 'Futebol', default => 'Vôlei' } }} · {{ $listedMeeting->team_size }}×{{ $listedMeeting->team_size }} · {{ $listedMeeting->status === 'active' ? 'ativo' : 'encerrado' }}
                        </option>
                    @endforeach
                </select>
            </label>
        @endif
        <form method="POST" action="{{ route('logout') }}" class="flex items-center gap-3 text-sm text-slate-300">
            @csrf
            <span>{{ auth()->user()->name }}</span>
            <button class="rounded-xl border border-slate-700 px-3 py-2 hover:bg-slate-800">Sair</button>
        </form>
    </header>

    @if ($this->meeting)
        <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-emerald-400/25 bg-emerald-400/10 p-4 sm:p-5" aria-label="Placar compartilhado">
            <div>
                <h2 class="font-bold text-emerald-300">Placar para TV e jogadores</h2>
                <p class="mt-1 text-sm text-slate-300">Exibe os times em jogo, o placar e o próximo time. Atualiza automaticamente.</p>
            </div>
            <a href="{{ route('scoreboard', ['token' => $this->meeting->display_token]) }}" target="_blank" rel="noopener noreferrer" class="rounded-xl bg-emerald-400 px-5 py-3 text-sm font-bold text-slate-950 hover:bg-emerald-300">Abrir placar ↗</a>
            <label class="w-full text-xs text-slate-400">Link para compartilhar
                <input type="text" readonly value="{{ route('scoreboard', ['token' => $this->meeting->display_token]) }}" x-on:click="$el.select()" class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200">
            </label>
        </section>

        <section class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold">Encontro de {{ $this->meeting->played_on->format('d/m/Y') }}</h2>
                <p class="text-sm text-slate-400">{{ match ($this->meeting->sport) { 'futsal' => 'Futsal', 'football' => 'Futebol', default => 'Vôlei' } }} · Formato {{ $this->meeting->team_size }}×{{ $this->meeting->team_size }} · {{ $this->participants->where('status', 'present')->count() }} presentes · {{ $this->teams->count() }} times formados · {{ $this->meeting->status === 'active' ? 'Em andamento' : 'Encerrado' }}</p>
            </div>
            @if ($this->meeting->status === 'completed')
            <div class="flex flex-wrap items-end gap-2">
                <label for="new-sport" class="text-sm text-slate-300">Modalidade
                    <select id="new-sport" wire:change="chooseSport($event.target.value)" class="mt-1 block rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                        <option value="volleyball" @selected($sport === 'volleyball')>Vôlei</option>
                        <option value="futsal" @selected($sport === 'futsal')>Futsal</option>
                        <option value="football" @selected($sport === 'football')>Futebol</option>
                    </select>
                </label>
                <label for="new-team-size" class="text-sm text-slate-300">Jogadores por time
                    <input id="new-team-size" type="number" min="2" max="11" step="1" wire:model="teamSize" class="mt-1 block w-28 rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                </label>
                <button wire:click="startMeeting" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold hover:bg-slate-800">Novo encontro</button>
            </div>
            @endif
        </section>

        @error('teamSize') <p role="alert" class="text-sm text-rose-300">{{ $message }}</p> @enderror

        @error('meeting') <p role="alert" class="rounded-xl bg-rose-950 px-4 py-3 text-rose-200">{{ $message }}</p> @enderror
        @error('score') <p role="alert" class="rounded-xl bg-rose-950 px-4 py-3 text-rose-200">{{ $message }}</p> @enderror
        @error('participant') <p role="alert" class="rounded-xl bg-rose-950 px-4 py-3 text-rose-200">{{ $message }}</p> @enderror

        <section class="grid gap-3 sm:grid-cols-3" aria-label="Resumo do encontro">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
                <p class="text-xs text-slate-400">Times completos possíveis</p>
                <p class="mt-1 text-2xl font-bold">{{ intdiv($this->participants->where('status', 'present')->count(), $this->meeting->team_size) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
                <p class="text-xs text-slate-400">Times completos formados</p>
                <p class="mt-1 text-2xl font-bold">{{ $this->teams->filter(fn ($team) => $team->participants->count() === $this->meeting->team_size)->count() }}</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
                <p class="text-xs text-slate-400">Pessoas disponíveis para completar times</p>
                <p class="mt-1 text-2xl font-bold">{{ $this->participants->where('status', 'present')->whereNull('team_id')->count() }}</p>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
            <h3 class="text-lg font-bold">Regras deste encontro</h3>
            <p class="mt-1 text-sm text-slate-400">Dinâmico coloca quem aguarda na próxima rodada e completa o time com jogadores do perdedor. Fixo mantém as equipes formadas; mudanças de jogadores são feitas nas ações de substituição. {{ $this->meeting->sport === 'volleyball' ? 'Meta e tempo' : 'O tempo' }} valem para partidas futuras.</p>
            @if ($this->meeting->status === 'active')
                <form wire:submit="saveSettings" class="mt-4 flex flex-wrap items-end gap-3">
                    <label class="text-sm">Peso de gênero <input type="number" min="0" max="100" wire:model="genderWeight" class="mt-1 block w-32 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white"></label>
                    <label class="text-sm">Peso de habilidade <input type="number" min="0" max="100" wire:model="skillWeight" class="mt-1 block w-32 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white"></label>
                    @if ($this->meeting->sport === 'volleyball')
                        <label class="text-sm">Pontos por partida <input type="number" min="5" max="30" wire:model="targetScore" class="mt-1 block w-32 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white"></label>
                    @endif
                    <label class="text-sm">Cronômetro (minutos, opcional) <input type="number" min="1" max="120" wire:model="durationMinutes" placeholder="Sem limite" class="mt-1 block w-40 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white"></label>
                    <label class="text-sm">Modo dos times
                        <select wire:model="teamMode" class="mt-1 block rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                            <option value="dynamic">Dinâmico</option>
                            <option value="fixed">Fixo</option>
                        </select>
                    </label>
                    <label class="text-sm">Desempate após vitórias
                        <select wire:model="rankingTiebreaker" class="mt-1 block rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                            <option value="win_rate">Aproveitamento</option>
                            <option value="point_balance">Saldo de {{ $this->meeting->sport === 'volleyball' ? 'pontos' : 'gols' }}</option>
                        </select>
                    </label>
                    <button class="rounded-xl border border-emerald-400 px-4 py-2 text-sm font-bold text-emerald-300 hover:bg-emerald-500/10">Salvar regras</button>
                </form>
                @error('genderWeight') <p role="alert" class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                @error('skillWeight') <p role="alert" class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                @error('rankingTiebreaker') <p role="alert" class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                @error('targetScore') <p role="alert" class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                @error('durationMinutes') <p role="alert" class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                @error('teamMode') <p role="alert" class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                @error('settings') <p role="alert" class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                <button wire:click="requestCloseMeeting" class="mt-5 rounded-xl border border-amber-400 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-500/10">Encerrar encontro</button>
            @else
                <p class="mt-3 text-sm text-slate-300">Modo: {{ $this->meeting->team_mode === 'dynamic' ? 'dinâmico' : 'fixo' }} · Gênero: {{ $this->meeting->gender_weight }} · Habilidade: {{ $this->meeting->skill_weight }} @if ($this->meeting->sport === 'volleyball') · Meta: {{ $this->meeting->target_score }} pontos @endif · Tempo: {{ $this->meeting->duration_minutes ? $this->meeting->duration_minutes.' min' : 'livre' }} · Desempate: {{ $this->meeting->ranking_tiebreaker === 'win_rate' ? 'aproveitamento' : 'saldo de '.($this->meeting->sport === 'volleyball' ? 'pontos' : 'gols') }}</p>
            @endif
        </section>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.7fr)_minmax(290px,1fr)]">
            <div class="flex flex-col gap-6">
                <section class="rounded-3xl border border-slate-800 bg-slate-900 p-5 shadow-xl sm:p-7">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold">Em jogo</h3>
                        @if ($this->currentMatch)
                            <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-bold text-emerald-300">Partida {{ $this->currentMatch->sequence }} @if ($this->meeting->sport === 'volleyball') · até {{ $this->currentMatch->target_score }} pontos @endif</span>
                        @endif
                    </div>
                    @if ($this->currentMatch)
                        @if ($this->currentMatch->duration_minutes !== null)
                            <div wire:key="timer-{{ $this->currentMatch->id }}-{{ $this->currentMatch->timer_started_at?->timestamp ?? 0 }}-{{ $this->currentMatch->timer_elapsed_seconds }}" class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-700 bg-slate-950 p-4"
                                x-data='{ base: {{ $this->currentMatch->timer_elapsed_seconds }}, startedAt: @json($this->currentMatch->timer_started_at?->toIso8601String()), now: Date.now(), duration: {{ $this->currentMatch->duration_minutes * 60 }}, interval: null, init() { this.interval = setInterval(() => this.now = Date.now(), 1000) }, destroy() { clearInterval(this.interval) }, get total() { return this.base + (this.startedAt ? Math.max(0, Math.floor((this.now - Date.parse(this.startedAt)) / 1000)) : 0) }, format(value) { return String(Math.floor(value / 60)).padStart(2, "0") + ":" + String(value % 60).padStart(2, "0") } }'>
                                <div>
                                    <p class="text-xs text-slate-400">Tempo restante</p>
                                    <p class="text-3xl font-black tabular-nums" x-text="format(Math.max(0, duration - total))" :class="total >= duration ? 'text-rose-300' : 'text-white'"></p>
                                    <p class="text-xs text-slate-400" x-show="total >= duration">Tempo previsto encerrado; confirme o resultado quando houver vencedor.</p>
                                </div>
                                @if ($this->meeting->status === 'active')
                                    <div class="flex gap-2">
                                        @if ($this->currentMatch->timer_started_at)
                                            <button wire:click="pauseTimer" class="rounded-xl border border-slate-700 px-3 py-2 text-sm font-semibold hover:bg-slate-800">Pausar</button>
                                        @else
                                            <button wire:click="startTimer" class="rounded-xl border border-emerald-400 px-3 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/10">{{ $this->currentMatch->timer_elapsed_seconds > 0 ? 'Retomar' : 'Iniciar tempo' }}</button>
                                        @endif
                                        @if ($this->currentMatch->score_started_at === null)
                                            <button wire:click="resetTimer" class="rounded-xl border border-slate-700 px-3 py-2 text-sm hover:bg-slate-800">Zerar</button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            @error('timer') <p role="alert" class="mb-3 text-sm text-rose-300">{{ $message }}</p> @enderror
                        @endif
                        <div class="grid grid-cols-2 gap-3 sm:gap-5">
                            @foreach (['home' => $this->currentMatch->homeTeam, 'away' => $this->currentMatch->awayTeam] as $side => $team)
                                <div wire:key="court-{{ $team->id }}" class="flex flex-col gap-4 rounded-2xl border border-slate-700 bg-slate-950 p-3 sm:p-5">
                                    <div>
                                        <h4 class="font-bold text-white">{{ $team->name }}</h4>
                                        <p class="text-xs text-slate-400">{{ $team->consecutive_games + 1 }}ª partida seguida</p>
                                        @if ($team->participants->count() < $this->meeting->team_size)
                                            <p class="mt-1 text-xs font-semibold text-amber-300">Incompleto · {{ $team->participants->count() }}/{{ $this->meeting->team_size }}</p>
                                        @endif
                                    </div>
                                    <div class="text-center text-5xl font-black tabular-nums sm:text-7xl">{{ $side === 'home' ? $this->currentMatch->home_score : $this->currentMatch->away_score }}</div>
                                    @if ($this->meeting->status === 'active')
                                    <div class="grid grid-cols-2 gap-2">
                                        <button wire:click="score('{{ $side }}', -1)" aria-label="Retirar {{ $this->meeting->sport === 'volleyball' ? 'ponto' : 'gol' }} de {{ $team->name }}" class="min-h-12 rounded-xl border border-slate-700 text-xl font-bold hover:bg-slate-800">−</button>
                                        <button wire:click="score('{{ $side }}', 1)" aria-label="Adicionar {{ $this->meeting->sport === 'volleyball' ? 'ponto' : 'gol' }} para {{ $team->name }}" class="min-h-12 rounded-xl bg-emerald-500 text-2xl font-black text-slate-950 hover:bg-emerald-400">+</button>
                                    </div>
                                    @endif
                                    <ul class="space-y-1 text-sm text-slate-300">
                                        @foreach ($team->participants as $participant)
                                            <li wire:key="court-player-{{ $participant->id }}">{{ $participant->player->name }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 rounded-2xl border border-slate-700 bg-slate-950 p-4 text-sm text-slate-300">
                            <p class="font-bold text-white">Equilíbrio dos times</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <p>{{ $this->currentMatch->homeTeam->name }}: {{ $this->balanceSummary['home_female'] }} mulheres · nível {{ $this->balanceSummary['home_skill'] }}</p>
                                <p>{{ $this->currentMatch->awayTeam->name }}: {{ $this->balanceSummary['away_female'] }} mulheres · nível {{ $this->balanceSummary['away_skill'] }}</p>
                            </div>
                            @if ($this->balanceSummary['gender_gap'] > 0 || $this->balanceSummary['skill_gap'] > 0)
                                <p class="mt-2 text-xs text-amber-200">Diferença de {{ $this->balanceSummary['gender_gap'] }} mulher(es) e {{ $this->balanceSummary['skill_gap'] }} ponto(s) de nível. A ordem de entrada foi preservada.</p>
                            @endif
                        </div>
                        @if ($this->meeting->sport === 'volleyball' && $this->currentMatch->home_score >= $this->currentMatch->target_score - 1 && $this->currentMatch->away_score >= $this->currentMatch->target_score - 1)
                            <p class="mt-4 rounded-xl bg-amber-500/10 px-4 py-3 text-sm text-amber-200">Desempate após {{ $this->currentMatch->target_score - 1 }}×{{ $this->currentMatch->target_score - 1 }}: {{ $this->currentMatch->homeTeam->name }} {{ $this->currentMatch->home_score - $this->currentMatch->target_score + 1 }}/3 · {{ $this->currentMatch->awayTeam->name }} {{ $this->currentMatch->away_score - $this->currentMatch->target_score + 1 }}/3 pontos extras. Vence quem chegar primeiro a {{ $this->currentMatch->target_score + 2 }}.</p>
                        @endif
                        @if ($this->meeting->status === 'active')
                        <div class="mt-5 flex flex-wrap gap-3">
                            @if ($this->canPromoteWaitingPlayers)
                                <button wire:click="promoteWaitingPlayers" class="rounded-xl border border-emerald-400 px-5 py-3 font-bold text-emerald-300 hover:bg-emerald-500/10">Colocar {{ $this->participants->where('status', 'present')->whereNull('team_id')->count() }} pessoas aguardando em quadra</button>
                            @endif
                            @if ($this->meeting->sport !== 'volleyball' && $this->currentMatch->home_score === $this->currentMatch->away_score)
                                <div class="w-full rounded-xl border border-amber-400/40 bg-amber-400/10 p-4">
                                    <p class="text-sm font-semibold text-amber-200">Placar empatado: escolha quem venceu o desempate para avançar a fila.</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button wire:click="requestConfirmDrawWinner('home', false)" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-950 hover:bg-slate-200">{{ $this->currentMatch->homeTeam->name }} venceu</button>
                                        <button wire:click="requestConfirmDrawWinner('away', false)" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-950 hover:bg-slate-200">{{ $this->currentMatch->awayTeam->name }} venceu</button>
                                    </div>
                                    @if ($this->vacancies->isNotEmpty())
                                        <p class="mt-3 text-xs text-amber-200">Se o próximo time ficará incompleto, autorize a próxima partida ao escolher o vencedor:</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <button wire:click="requestConfirmDrawWinner('home', true)" class="rounded-xl border border-amber-400 px-4 py-2 text-sm font-semibold text-amber-200">{{ $this->currentMatch->homeTeam->name }} · jogo incompleto</button>
                                            <button wire:click="requestConfirmDrawWinner('away', true)" class="rounded-xl border border-amber-400 px-4 py-2 text-sm font-semibold text-amber-200">{{ $this->currentMatch->awayTeam->name }} · jogo incompleto</button>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <button wire:click="requestConfirmResult" class="rounded-xl bg-white px-5 py-3 font-bold text-slate-950 hover:bg-slate-200">Confirmar resultado</button>
                                @if ($this->vacancies->isNotEmpty())
                                    <button wire:click="requestConfirmIncompleteResult" class="rounded-xl border border-amber-400 px-5 py-3 text-sm font-semibold text-amber-200 hover:bg-amber-500/10">Autorizar próximo jogo incompleto</button>
                                @endif
                            @endif
                            @if ($this->history->isNotEmpty())
                                <button wire:click="requestUndoResult" class="rounded-xl border border-slate-700 px-5 py-3 text-sm font-semibold hover:bg-slate-800">Desfazer último</button>
                            @endif
                        </div>
                        @endif
                        @if ($this->canSwap)
                            <form wire:submit="swapPlayers" class="mt-6 rounded-2xl border border-slate-700 bg-slate-950 p-4">
                                <h4 class="font-bold">Ajustar os times</h4>
                                <p class="mt-1 text-xs text-slate-400">Troque uma pessoa de cada lado antes de começar o placar. A ordem de chegada continua respeitada.</p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label class="text-sm">{{ $this->currentMatch->homeTeam->name }}
                                        <select wire:model="firstSwapParticipantId" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-white">
                                            <option value="">Escolha uma pessoa</option>
                                            @foreach ($this->homePlayers as $participant)
                                                <option wire:key="swap-home-{{ $participant->id }}" value="{{ $participant->id }}">{{ $participant->player->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="text-sm">{{ $this->currentMatch->awayTeam->name }}
                                        <select wire:model="secondSwapParticipantId" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-white">
                                            <option value="">Escolha uma pessoa</option>
                                            @foreach ($this->awayPlayers as $participant)
                                                <option wire:key="swap-away-{{ $participant->id }}" value="{{ $participant->id }}">{{ $participant->player->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                @error('swap') <p class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                                @error('firstSwapParticipantId') <p class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                                @error('secondSwapParticipantId') <p class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                                <button class="mt-3 rounded-xl border border-emerald-400 px-4 py-2 text-sm font-bold text-emerald-300 hover:bg-emerald-500/10">Trocar jogadores</button>
                            </form>
                        @endif
                    @else
                        <p class="rounded-2xl border border-dashed border-slate-700 p-8 text-center text-slate-400">{{ $this->meeting->status === 'active' ? 'Adicione '.($this->meeting->team_size * 2).' pessoas para iniciar a primeira partida.' : 'Encontro encerrado. Consulte o histórico e o ranking abaixo.' }}</p>
                    @endif
                </section>

                @if ($this->meeting->status === 'active' && $this->vacancies->isNotEmpty())
                    <section class="rounded-3xl border border-amber-500/40 bg-amber-500/10 p-5 sm:p-7">
                        <h3 class="mb-4 text-lg font-bold text-amber-200">Times incompletos</h3>
                        <div class="space-y-4">
                            @foreach ($this->vacancies as $vacancy)
                                <div wire:key="vacancy-{{ $vacancy['team']->id }}" class="rounded-2xl bg-slate-950 p-4 text-sm">
                                    <strong>{{ $vacancy['team']->name }} · {{ $vacancy['team']->participants->count() }}/{{ $this->meeting->team_size }}</strong>
                                    @if ($vacancy['suggestion'])
                                        <p class="mt-2 text-slate-300">Sugestão: {{ $vacancy['suggestion']->player->name }}, da posição {{ $vacancy['suggestion']->arrival_order }}. {{ $vacancy['suggestion']->team->name }} ficará com {{ $this->meeting->team_size - 1 }}/{{ $this->meeting->team_size }} jogadores.</p>
                                        @if ($vacancy['impact'])
                                            <p class="mt-2 text-xs text-slate-400">Após o empréstimo: {{ $vacancy['team']->name }} terá {{ $vacancy['impact']['target_women'] }} mulher(es) e nível {{ $vacancy['impact']['target_skill'] }}; {{ $vacancy['suggestion']->team->name }} terá {{ $vacancy['impact']['source_women'] }} mulher(es) e nível {{ $vacancy['impact']['source_skill'] }}.</p>
                                        @endif
                                        <button wire:click="requestLoanPlayer({{ $vacancy['team']->id }}, {{ $vacancy['suggestion']->id }})" class="mt-3 rounded-xl bg-amber-400 px-4 py-2 font-bold text-slate-950 hover:bg-amber-300">Confirmar empréstimo</button>
                                    @else
                                        <p class="mt-2 text-slate-400">Aguardando a chegada de uma pessoa disponível para completar este time.</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="rounded-3xl border border-slate-800 bg-slate-900 p-5 sm:p-7">
                    <h3 class="mb-4 text-lg font-bold">Fila de times</h3>
                    @if ($this->teams->where('status', 'waiting')->isNotEmpty())
                        <p class="mb-4 text-sm text-emerald-300">Próximos na fila: {{ $this->teams->where('status', 'waiting')->sortBy('queue_position')->take(2)->pluck('name')->join(' e ') }}</p>
                    @endif
                    @forelse ($this->teams->where('status', 'waiting')->sortBy('queue_position') as $team)
                        <div wire:key="queue-{{ $team->id }}" class="mb-3 rounded-2xl border border-slate-700 bg-slate-950 p-4">
                            <div class="flex items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-emerald-500/15 font-bold text-emerald-300">{{ $loop->iteration }}</span><strong>{{ $team->name }}</strong><span class="ml-auto text-xs text-slate-400">{{ $team->participants->count() }}/{{ $this->meeting->team_size }}</span></div>
                            <p class="mt-2 text-sm text-slate-400">{{ $team->participants->pluck('player.name')->join(', ') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">Nenhum time aguardando.</p>
                    @endforelse
                </section>
            </div>

            <div class="flex flex-col gap-6">
                @if ($this->meeting->status === 'active')
                <section class="rounded-3xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
                    <h3 class="text-lg font-bold">Registrar chegada</h3>
                    <p class="mt-1 text-sm text-slate-400">A ordem desta lista define quem joga primeiro.</p>
                    @if ($this->availablePlayers->isNotEmpty())
                        <form wire:submit="addExistingPlayer" class="mt-5 flex flex-col gap-3 rounded-2xl border border-slate-700 bg-slate-950 p-4">
                            <label class="text-sm font-medium">Pessoa já cadastrada
                                <select wire:model="existingPlayerId" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-white">
                                    <option value="">Selecione uma pessoa</option>
                                    @foreach ($this->availablePlayers as $player)
                                        <option wire:key="known-player-{{ $player->id }}" value="{{ $player->id }}">{{ $player->name }}{{ $player->participants->isNotEmpty() ? ' · retornando' : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @error('existingPlayerId') <span class="text-sm text-rose-300">{{ $message }}</span> @enderror
                            <button class="rounded-xl border border-emerald-400 px-4 py-3 font-bold text-emerald-300 hover:bg-emerald-500/10" wire:loading.attr="disabled" wire:target="addExistingPlayer">Adicionar pessoa existente</button>
                        </form>
                    @endif
                    <form wire:submit="addPlayer" class="mt-5 flex flex-col gap-3">
                        <p class="text-sm font-semibold text-slate-300">Cadastrar nova pessoa</p>
                        <label class="text-sm font-medium">Nome <input wire:model="name" required maxlength="100" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-white" placeholder="Nome do jogador"></label>
                        @error('name') <span class="text-sm text-rose-300">{{ $message }}</span> @enderror
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-sm font-medium">Gênero <select wire:model="gender" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3"><option value="female">Mulher</option><option value="male">Homem</option></select></label>
                            <label class="text-sm font-medium">Nível <select wire:model="skillLevel" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3">@for ($level = 1; $level <= 5; $level++) <option value="{{ $level }}">{{ $level }}</option> @endfor</select></label>
                        </div>
                        <button class="mt-2 rounded-xl bg-emerald-500 px-4 py-3 font-bold text-slate-950 hover:bg-emerald-400" wire:loading.attr="disabled" wire:target="addPlayer">Adicionar à lista</button>
                    </form>
                </section>
                @endif

                <section class="rounded-3xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
                    <h3 class="mb-4 text-lg font-bold">Lista de chegada</h3>
                    <ol class="max-h-96 space-y-2 overflow-y-auto">
                        @foreach ($this->participants as $participant)
                            <li wire:key="arrival-{{ $participant->id }}" class="flex items-center gap-3 rounded-xl bg-slate-950 px-3 py-2 text-sm">
                                <span class="w-6 text-slate-500">{{ $participant->arrival_order }}.</span>
                                <span class="min-w-0 flex-1 truncate">{{ $participant->player->name }}</span>
                                <span class="text-xs text-slate-400">
                                    @if ($participant->status === 'left')
                                        Foi embora
                                    @elseif ($participant->team?->status === 'court')
                                        Em jogo · {{ $participant->team->name }}
                                    @elseif ($participant->team)
                                        Aguardando · {{ $participant->team->name }}
                                    @else
                                        Disponível
                                    @endif
                                </span>
                                @if ($this->meeting->status === 'active' && $participant->status === 'present')
                                    <button wire:click="requestDepartPlayer({{ $participant->id }})" aria-label="Registrar saída de {{ $participant->player->name }}" class="rounded-lg border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:bg-slate-800">Saiu</button>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                    @if ($this->participants->where('status', 'present')->whereNull('team_id')->count())
                        <p class="mt-4 text-xs text-slate-400">{{ $this->participants->where('status', 'present')->whereNull('team_id')->count() }} aguardando para completar o próximo time.</p>
                    @endif
                </section>
            </div>
        </div>

        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-5 sm:p-7">
            <h3 class="mb-4 text-lg font-bold">Histórico e ranking</h3>
            @if ($this->history->isEmpty())
                <p class="text-sm text-slate-400">Os resultados confirmados aparecerão aqui.</p>
            @else
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="space-y-2">
                        @foreach ($this->history as $match)
                            <div wire:key="history-{{ $match->id }}" class="rounded-xl bg-slate-950 px-4 py-3 text-sm">
                                <strong>#{{ $match->sequence }} {{ $match->homeTeam->name }} {{ $match->home_score }} × {{ $match->away_score }} {{ $match->awayTeam->name }}</strong>
                                @if ($this->meeting->sport !== 'volleyball' && $match->home_score === $match->away_score)
                                    <p class="mt-1 text-xs font-semibold text-emerald-300">Desempate: {{ $match->winner_team_id === $match->home_team_id ? $match->homeTeam->name : $match->awayTeam->name }} venceu</p>
                                @endif
                                @if ($match->duration_minutes !== null)
                                    <p class="mt-1 text-xs text-slate-400">Duração: {{ intdiv($match->timer_elapsed_seconds, 60) }} min {{ $match->timer_elapsed_seconds % 60 }} s</p>
                                @endif
                                <p class="mt-1 text-xs text-slate-400">{{ collect($match->home_roster)->pluck('name')->join(', ') }} × {{ collect($match->away_roster)->pluck('name')->join(', ') }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm"><thead class="text-slate-400"><tr><th class="pb-3">Time</th><th class="pb-3">J</th><th class="pb-3">V</th><th class="pb-3">D</th><th class="pb-3">%</th><th class="pb-3">Saldo {{ $this->meeting->sport === 'volleyball' ? 'de pontos' : 'de gols' }}</th></tr></thead>
                        <tbody>
                            @foreach ($this->ranking as $row)
                                <tr wire:key="ranking-{{ $row['team_id'] }}" class="border-t border-slate-800"><td class="py-2">{{ $row['name'] }}</td><td>{{ $row['played'] }}</td><td>{{ $row['wins'] }}</td><td>{{ $row['losses'] }}</td><td>{{ number_format($row['win_rate'] * 100, 0) }}%</td><td>{{ $row['point_balance'] }}</td></tr>
                            @endforeach
                        </tbody></table>
                    </div>
                </div>
            @endif
        </section>

        @if ($this->changes->isNotEmpty())
            <section class="rounded-3xl border border-slate-800 bg-slate-900 p-5 sm:p-7">
                <h3 class="mb-4 text-lg font-bold">Saídas e substituições</h3>
                <ol class="space-y-2 text-sm">
                    @foreach ($this->changes as $change)
                        <li wire:key="change-{{ $change->id }}" class="rounded-xl bg-slate-950 px-4 py-3">
                            @if ($change->type === 'departure')
                                {{ $change->outgoingParticipant?->player?->name }} saiu{{ $change->team ? ' do '.$change->team->name : ' da lista de espera' }}.
                            @elseif ($change->type === 'loan')
                                {{ $change->incomingParticipant?->player?->name }} foi emprestado de {{ $change->sourceTeam?->name }} para {{ $change->team?->name }}.
                            @elseif ($change->type === 'rotation')
                                {{ $change->incomingParticipant?->player?->name }} passou de {{ $change->sourceTeam?->name }} para {{ $change->team?->name }} para completar a próxima partida.
                            @elseif ($change->type === 'formation')
                                {{ $change->incomingParticipant?->player?->name }} entrou em {{ $change->team?->name }} após aguardar na lista.
                            @elseif ($change->type === 'return')
                                {{ $change->incomingParticipant?->player?->name }} voltou ao encontro e entrou no fim da lista de chegada.
                            @elseif ($change->type === 'swap')
                                {{ $change->outgoingParticipant?->player?->name }} e {{ $change->incomingParticipant?->player?->name }} trocaram entre {{ $change->team?->name }} e {{ $change->sourceTeam?->name }}.
                            @elseif ($change->type === 'balance')
                                {{ $change->incomingParticipant?->player?->name }} passou de {{ $change->sourceTeam?->name }} para {{ $change->team?->name }} no equilíbrio automático.
                            @else
                                {{ $change->incomingParticipant?->player?->name }} entrou em {{ $change->team?->name }} para completar a equipe.
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    @else
        <section class="mx-auto mt-12 w-full max-w-lg rounded-3xl border border-slate-800 bg-slate-900 p-7 text-center">
            <h2 class="text-2xl font-bold">Começar encontro</h2>
            <p class="mt-2 text-sm text-slate-400">Escolha a modalidade e o formato. Depois, registre os jogadores na ordem em que chegarem.</p>
            <label for="sport" class="mt-6 block text-left text-sm font-medium text-slate-300">Modalidade
                <select id="sport" wire:change="chooseSport($event.target.value)" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
                    <option value="volleyball" @selected($sport === 'volleyball')>Vôlei</option>
                    <option value="futsal" @selected($sport === 'futsal')>Futsal</option>
                    <option value="football" @selected($sport === 'football')>Futebol</option>
                </select>
            </label>
            @error('sport') <p role="alert" class="mt-2 text-left text-sm text-rose-300">{{ $message }}</p> @enderror
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                @foreach ([2, 3, 4, 5, 6] as $size)
                    <button wire:click="$set('teamSize', {{ $size }})" class="rounded-xl border px-5 py-3 font-bold {{ (int) $teamSize === $size ? 'border-emerald-400 bg-emerald-500/15 text-emerald-300' : 'border-slate-700' }}">{{ $size }}×{{ $size }}</button>
                @endforeach
            </div>
            <label for="team-size" class="mt-5 block text-left text-sm font-medium text-slate-300">Ou escolha a quantidade de jogadores por time (2 a 11)
                <input id="team-size" type="number" min="2" max="11" step="1" wire:model="teamSize" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
            </label>
            <p class="mt-2 text-left text-xs text-slate-400">Futsal sugere 5×5; futebol sugere 11×11. Você pode ajustar o número para seu grupo.</p>
            @error('teamSize') <p role="alert" class="mt-2 text-left text-sm text-rose-300">{{ $message }}</p> @enderror
            <button wire:click="startMeeting" class="mt-6 w-full rounded-xl bg-emerald-500 px-5 py-3 font-bold text-slate-950 hover:bg-emerald-400">Criar encontro</button>
        </section>
    @endif
</div>
