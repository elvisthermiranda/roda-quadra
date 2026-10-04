<?php

use App\Models\Meeting;
use App\Models\Team;
use App\Models\VolleyballMatch;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('scoreboard-layout')] class extends Component
{
    #[Locked]
    public string $token;

    public function mount(string $token): void
    {
        Meeting::query()->where('display_token', $token)->firstOrFail();
        $this->token = $token;
    }

    #[Computed]
    public function meeting(): Meeting
    {
        return Meeting::query()->where('display_token', $this->token)->firstOrFail();
    }

    #[Computed]
    public function currentMatch(): ?VolleyballMatch
    {
        return $this->meeting->matches()
            ->with([
                'homeTeam.participants' => fn ($query) => $query->where('status', 'present')->with('player')->orderBy('arrival_order'),
                'awayTeam.participants' => fn ($query) => $query->where('status', 'present')->with('player')->orderBy('arrival_order'),
            ])
            ->where('status', 'playing')->first();
    }

    #[Computed]
    public function lastMatch(): ?VolleyballMatch
    {
        return $this->meeting->matches()->with(['homeTeam', 'awayTeam'])
            ->where('status', 'confirmed')->orderByDesc('sequence')->first();
    }

    #[Computed]
    public function displayedMatch(): ?VolleyballMatch
    {
        return $this->currentMatch ?? $this->lastMatch;
    }

    #[Computed]
    public function homePlayers(): Collection
    {
        if ($this->currentMatch) {
            return $this->currentMatch->homeTeam->participants->pluck('player.name')->values();
        }

        return collect($this->lastMatch?->home_roster ?? [])->pluck('name');
    }

    #[Computed]
    public function awayPlayers(): Collection
    {
        if ($this->currentMatch) {
            return $this->currentMatch->awayTeam->participants->pluck('player.name')->values();
        }

        return collect($this->lastMatch?->away_roster ?? [])->pluck('name');
    }

    #[Computed]
    public function nextTeam(): ?Team
    {
        if ($this->meeting->status !== 'active') {
            return null;
        }

        return $this->meeting->teams()
            ->with(['participants' => fn ($query) => $query->where('status', 'present')->with('player')->orderBy('arrival_order')])
            ->where('status', 'waiting')->orderBy('queue_position')->first();
    }

    #[Computed]
    public function waitingPlayers(): Collection
    {
        return $this->meeting->participants()->with('player')->where('status', 'present')
            ->whereNull('team_id')->orderBy('arrival_order')->get();
    }

    #[Computed]
    public function presentCount(): int
    {
        return $this->meeting->participants()->where('status', 'present')->count();
    }
};
?>

<div wire:poll.5s.keep-alive class="relative isolate min-h-screen overflow-hidden bg-slate-950 text-slate-100">
    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[38rem] bg-[radial-gradient(ellipse_at_50%_0%,rgba(16,185,129,0.17),transparent_65%)]"></div>

    <div class="mx-auto flex min-h-screen w-full max-w-[1600px] flex-col gap-5 px-4 py-5 sm:px-6 lg:gap-7 lg:px-10 lg:py-8 2xl:gap-9">
        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-5">
            <div class="flex items-center gap-3">
                <span aria-hidden="true" class="grid size-10 place-items-center rounded-2xl bg-emerald-400 text-lg font-black text-slate-950 sm:size-12">↻</span>
                <div>
                    <p class="text-lg font-black tracking-tight sm:text-2xl">Roda<span class="text-emerald-400">Quadra</span></p>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Placar compartilhado</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-bold sm:text-sm">
                <span class="rounded-full border border-slate-700 bg-slate-900 px-4 py-2 text-slate-300">{{ match ($this->meeting->sport) { 'futsal' => 'Futsal', 'football' => 'Futebol', default => 'Vôlei' } }} · {{ $this->meeting->team_size }}×{{ $this->meeting->team_size }}</span>
                <span class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-4 py-2 text-emerald-300"><span aria-hidden="true">●</span> {{ $this->meeting->status === 'active' ? 'Em andamento' : 'Encerrado' }}</span>
                <button type="button" x-on:click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()" class="rounded-full border border-slate-700 px-4 py-2 text-slate-200 transition hover:bg-slate-800">Tela cheia</button>
            </div>
        </header>

        <main class="flex flex-1 flex-col gap-5 lg:gap-7">
            @if ($this->displayedMatch)
                <section aria-label="Placar da partida" class="overflow-hidden rounded-[2rem] border border-slate-700 bg-slate-900/90 p-5 shadow-2xl shadow-black/20 sm:p-8 lg:p-10">
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs font-bold uppercase tracking-[0.2em] text-slate-400 sm:text-sm">
                        <span>{{ $this->currentMatch ? 'Em jogo agora' : 'Último resultado' }}</span>
                        <span>Partida {{ $this->displayedMatch->sequence }} · {{ $this->meeting->played_on->format('d/m/Y') }}</span>
                    </div>

                    <div role="group" aria-label="Placar: {{ $this->displayedMatch->homeTeam->name }} {{ $this->displayedMatch->home_score }}, {{ $this->displayedMatch->awayTeam->name }} {{ $this->displayedMatch->away_score }}" class="mt-6 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 text-center sm:gap-5 lg:mt-8 lg:gap-12">
                        <div class="min-w-0">
                            <p class="break-words text-base font-black text-emerald-300 sm:text-3xl lg:text-5xl">{{ $this->displayedMatch->homeTeam->name }}</p>
                            <p class="mt-1 text-[clamp(4rem,14vw,12rem)] font-black leading-none tabular-nums tracking-tighter text-white">{{ $this->displayedMatch->home_score }}</p>
                        </div>
                        <span aria-hidden="true" class="text-2xl font-black text-slate-600 sm:text-5xl lg:text-7xl">×</span>
                        <div class="min-w-0">
                            <p class="break-words text-base font-black text-sky-300 sm:text-3xl lg:text-5xl">{{ $this->displayedMatch->awayTeam->name }}</p>
                            <p class="mt-1 text-[clamp(4rem,14vw,12rem)] font-black leading-none tabular-nums tracking-tighter text-white">{{ $this->displayedMatch->away_score }}</p>
                        </div>
                    </div>

                    @if ($this->meeting->sport !== 'volleyball' && $this->displayedMatch->status === 'confirmed' && $this->displayedMatch->home_score === $this->displayedMatch->away_score)
                        <p class="mt-4 text-center text-sm font-semibold text-emerald-300 sm:text-lg">Vencedor no desempate: {{ $this->displayedMatch->winner_team_id === $this->displayedMatch->home_team_id ? $this->displayedMatch->homeTeam->name : $this->displayedMatch->awayTeam->name }}</p>
                    @endif

                    <div class="mt-8 grid gap-4 md:grid-cols-2 lg:mt-10 lg:gap-6">
                        <div class="rounded-3xl border border-emerald-400/20 bg-slate-950/80 p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-bold text-emerald-300 sm:text-2xl">{{ $this->displayedMatch->homeTeam->name }}</h2><span class="text-sm text-slate-500">{{ $this->homePlayers->count() }} jogadores</span></div>
                            <ul class="mt-4 grid gap-x-6 gap-y-2 text-sm text-slate-200 sm:grid-cols-2 lg:text-lg">
                                @foreach ($this->homePlayers as $playerName)
                                    <li wire:key="home-player-{{ $loop->index }}" class="flex items-center gap-2"><span aria-hidden="true" class="size-1.5 shrink-0 rounded-full bg-emerald-400"></span><span class="min-w-0 break-words">{{ $playerName }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="rounded-3xl border border-sky-400/20 bg-slate-950/80 p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-bold text-sky-300 sm:text-2xl">{{ $this->displayedMatch->awayTeam->name }}</h2><span class="text-sm text-slate-500">{{ $this->awayPlayers->count() }} jogadores</span></div>
                            <ul class="mt-4 grid gap-x-6 gap-y-2 text-sm text-slate-200 sm:grid-cols-2 lg:text-lg">
                                @foreach ($this->awayPlayers as $playerName)
                                    <li wire:key="away-player-{{ $loop->index }}" class="flex items-center gap-2"><span aria-hidden="true" class="size-1.5 shrink-0 rounded-full bg-sky-400"></span><span class="min-w-0 break-words">{{ $playerName }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </section>
            @else
                <section class="grid min-h-72 place-items-center rounded-[2rem] border border-dashed border-slate-700 bg-slate-900/80 p-8 text-center lg:min-h-96">
                    <div>
                        @if ($this->meeting->status === 'active')
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-400">Aguardando início</p>
                            <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-5xl">A primeira partida vem aí.</h1>
                            <p class="mt-4 text-slate-400">{{ $this->presentCount }} de {{ $this->meeting->team_size * 2 }} jogadores necessários para formar dois times.</p>
                        @else
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-400">Encontro encerrado</p>
                            <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-5xl">Nenhuma partida foi registrada.</h1>
                        @endif
                    </div>
                </section>
            @endif

            <section aria-label="Próximo time" class="rounded-[2rem] border border-amber-400/30 bg-amber-400/10 p-5 sm:p-7 lg:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-amber-300 sm:text-sm">Na sequência</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight sm:text-4xl lg:text-5xl">{{ $this->nextTeam?->name ?? ($this->meeting->status === 'active' ? 'Próximo time em formação' : 'Encontro encerrado') }}</h2>
                    </div>
                    @if ($this->nextTeam)
                        <span class="rounded-full border border-amber-300/30 px-4 py-2 text-sm font-bold text-amber-200">{{ $this->nextTeam->participants->count() }}/{{ $this->meeting->team_size }} jogadores</span>
                    @endif
                </div>
                @if ($this->nextTeam)
                    <ul class="mt-5 flex flex-wrap gap-2 lg:gap-3">
                        @foreach ($this->nextTeam->participants as $participant)
                            <li wire:key="next-player-{{ $participant->id }}" class="rounded-full border border-amber-300/20 bg-slate-950/60 px-4 py-2 text-sm font-semibold text-slate-100 lg:text-lg">{{ $participant->player->name }}</li>
                        @endforeach
                    </ul>
                @elseif ($this->meeting->status === 'active')
                    @if ($this->waitingPlayers->isNotEmpty())
                        <p class="mt-3 text-sm text-amber-100/80 sm:text-base">{{ $this->waitingPlayers->count() }} {{ $this->waitingPlayers->count() === 1 ? 'pessoa aguardando' : 'pessoas aguardando' }} para formar o próximo time.</p>
                        <ul class="mt-4 flex flex-wrap gap-2 lg:gap-3">
                            @foreach ($this->waitingPlayers as $participant)
                                <li wire:key="waiting-player-{{ $participant->id }}" class="rounded-full border border-amber-300/20 bg-slate-950/60 px-4 py-2 text-sm font-semibold text-slate-100 lg:text-lg">{{ $participant->player->name }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-sm text-amber-100/80 sm:text-base">Nenhum time está aguardando na fila.</p>
                    @endif
                @else
                    <p class="mt-3 text-sm text-amber-100/80 sm:text-base">A fila deste encontro foi encerrada.</p>
                @endif
            </section>
        </main>

        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-800 pt-4 text-xs text-slate-500 sm:text-sm">
            <span>RodaQuadra · Placar para acompanhar de qualquer tela</span>
            <span>Atualização automática a cada 5 segundos</span>
        </footer>
    </div>
</div>
