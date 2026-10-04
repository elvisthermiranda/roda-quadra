<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Organize jogos de vôlei, futsal e futebol. Forme times, acompanhe a fila e registre os placares em um só lugar.">
    <title>RodaQuadra · Seu jogo em movimento</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 font-sans text-slate-100 antialiased">
    <div class="relative isolate overflow-hidden">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[46rem] bg-[radial-gradient(ellipse_at_70%_15%,rgba(16,185,129,0.18),transparent_52%),radial-gradient(ellipse_at_10%_55%,rgba(132,204,22,0.09),transparent_45%)]"></div>

        <header class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8 lg:px-10">
            <a href="{{ route('home') }}" class="group inline-flex items-center gap-3 font-black tracking-tight" aria-label="RodaQuadra, início">
                <span class="grid size-10 place-items-center rounded-2xl bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-500/20 transition-transform group-hover:rotate-12">
                    <svg aria-hidden="true" viewBox="0 0 32 32" fill="none" class="size-6" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="16" cy="16" r="11"></circle>
                        <path d="M16 5c4 3 6 7 6 11s-2 8-6 11M16 5c-4 3-6 7-6 11s2 8 6 11M5 16h22"></path>
                    </svg>
                </span>
                <span class="text-xl">Roda<span class="text-emerald-400">Quadra</span></span>
            </a>

            <nav aria-label="Navegação principal" class="hidden items-center gap-8 text-sm font-medium text-slate-300 md:flex">
                <a href="#como-funciona" class="transition hover:text-white">Como funciona</a>
                <a href="#recursos" class="transition hover:text-white">Recursos</a>
            </nav>

            <div class="flex items-center gap-2 sm:gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-full bg-emerald-400 px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-emerald-300 sm:px-5">Abrir painel <span aria-hidden="true">↗</span></a>
                @else
                    <a href="{{ route('login') }}" class="rounded-full px-3 py-2.5 text-sm font-semibold text-slate-300 transition hover:text-white">Entrar</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-emerald-400 px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-emerald-300 sm:px-5">Criar conta <span aria-hidden="true">↗</span></a>
                @endauth
            </div>
        </header>

        <main>
            <section class="mx-auto grid max-w-7xl items-center gap-12 px-5 pb-24 pt-14 sm:px-8 md:pt-20 lg:grid-cols-[1fr_0.95fr] lg:gap-16 lg:px-10 lg:pb-32 lg:pt-28">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-400/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">
                        <span class="size-2 rounded-full bg-emerald-400"></span>
                        Vôlei, futsal e futebol
                    </div>
                    <h1 class="mt-7 text-5xl font-black leading-[1.04] tracking-tight sm:text-6xl lg:text-7xl">
                        Menos espera.<br>
                        <span class="text-emerald-400">Mais jogo.</span>
                    </h1>
                    <p class="mt-7 max-w-xl text-lg leading-8 text-slate-300">Chegadas, times, fila e placar no mesmo lugar. Organize a rodada com clareza e deixe todo mundo saber quem joga agora e quem entra depois.</p>
                    <div class="mt-9 flex flex-wrap items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-emerald-400 px-6 py-3 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-300">Ir para meu painel <span class="ml-2" aria-hidden="true">→</span></a>
                        @else
                            <a href="{{ route('register') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-emerald-400 px-6 py-3 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-300">Organizar meu grupo <span class="ml-2" aria-hidden="true">→</span></a>
                        @endauth
                        <a href="#como-funciona" class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-slate-700 px-6 py-3 font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-900">Ver como funciona</a>
                    </div>
                    <div class="mt-10 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-400">
                        <span>✓ Sua lista de jogadores</span>
                        <span>✓ Times fixos ou dinâmicos</span>
                        <span>✓ Histórico de partidas</span>
                    </div>
                </div>

                <div class="relative mx-auto w-full max-w-xl">
                    <div aria-hidden="true" class="absolute -inset-4 -z-10 rounded-[2.5rem] bg-emerald-400/10 blur-3xl"></div>
                    <div class="overflow-hidden rounded-[2rem] border border-slate-700/80 bg-slate-900/95 p-4 shadow-2xl shadow-black/30 sm:p-6">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-800 pb-5">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-400">Exemplo de uma rodada</p>
                                <h2 class="mt-1 text-xl font-bold">Vôlei de sexta</h2>
                            </div>
                            <span class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-xs font-bold text-emerald-300">● Em andamento</span>
                        </div>

                        <div class="mt-5 rounded-2xl border border-slate-700 bg-slate-950 p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3 text-xs font-bold uppercase tracking-widest text-slate-400">
                                <span>Na quadra</span>
                                <span>Partida 03</span>
                            </div>
                            <div class="mt-7 grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center">
                                <div>
                                    <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-emerald-400/15 text-lg font-black text-emerald-300">A</span>
                                    <p class="mt-3 font-bold">Time A</p>
                                </div>
                                <div class="flex items-center gap-2 text-4xl font-black tabular-nums tracking-tight sm:gap-4 sm:text-5xl"><span>15</span><span class="text-lg text-slate-600">×</span><span>12</span></div>
                                <div>
                                    <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-sky-400/15 text-lg font-black text-sky-300">B</span>
                                    <p class="mt-3 font-bold">Time B</p>
                                </div>
                            </div>
                            <div class="mt-7 h-1.5 overflow-hidden rounded-full bg-slate-800"><div class="h-full w-[58%] rounded-full bg-emerald-400"></div></div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Próximos da fila</p>
                                <p class="mt-2 text-sm font-semibold text-slate-200">Time C <span class="text-slate-500">· aguardando</span></p>
                            </div>
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Controle da rodada</p>
                                <p class="mt-2 text-sm font-semibold text-slate-200">Placar e tempo em um só lugar</p>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -bottom-5 -left-4 rounded-2xl border border-emerald-400/20 bg-slate-800 px-4 py-3 text-sm font-semibold text-emerald-300 shadow-xl sm:-left-7">↻ Todo mundo entra na roda</div>
                </div>
            </section>

            <section id="como-funciona" class="border-y border-slate-800 bg-slate-900/60 px-5 py-20 sm:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-400">Simples de usar</p>
                    <h2 class="mt-3 max-w-xl text-3xl font-black tracking-tight sm:text-4xl">Da chegada à última partida.</h2>
                    <div class="mt-10 grid gap-4 md:grid-cols-3">
                        <article class="rounded-3xl border border-slate-800 bg-slate-950 p-6">
                            <span class="text-sm font-black text-emerald-400">01 / CHEGADAS</span>
                            <h3 class="mt-5 text-xl font-bold">Monte seu grupo</h3>
                            <p class="mt-3 leading-7 text-slate-400">Cadastre os jogadores e marque quem chegou. A ordem de chegada ajuda a organizar a fila.</p>
                        </article>
                        <article class="rounded-3xl border border-slate-800 bg-slate-950 p-6">
                            <span class="text-sm font-black text-emerald-400">02 / TIMES</span>
                            <h3 class="mt-5 text-xl font-bold">Escolha o seu jogo</h3>
                            <p class="mt-3 leading-7 text-slate-400">Escolha vôlei, futsal ou futebol, defina até 11 jogadores por time e use o modo fixo ou dinâmico.</p>
                        </article>
                        <article class="rounded-3xl border border-slate-800 bg-slate-950 p-6">
                            <span class="text-sm font-black text-emerald-400">03 / PARTIDAS</span>
                            <h3 class="mt-5 text-xl font-bold">Acompanhe a rodada</h3>
                            <p class="mt-3 leading-7 text-slate-400">Atualize o placar, acompanhe o tempo e veja os próximos times da fila.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="recursos" class="mx-auto grid max-w-7xl gap-10 px-5 py-20 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-center lg:gap-20 lg:px-10 lg:py-28">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-400">Feito para quem organiza</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Seu grupo tem o próprio espaço.</h2>
                    <p class="mt-5 leading-8 text-slate-400">Cada conta guarda seus jogadores, encontros e resultados. Você retoma a organização de onde parou, sem misturar listas de grupos diferentes.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-3xl border border-slate-800 bg-slate-900 p-6"><span class="text-2xl text-emerald-400">◎</span><h3 class="mt-4 font-bold">Fila visível</h3><p class="mt-2 text-sm leading-6 text-slate-400">Saiba quem está jogando e quem espera a próxima partida.</p></div>
                    <div class="rounded-3xl border border-slate-800 bg-slate-900 p-6"><span class="text-2xl text-emerald-400">◷</span><h3 class="mt-4 font-bold">Tempo de jogo</h3><p class="mt-2 text-sm leading-6 text-slate-400">Use o cronômetro quando quiser controlar a duração da partida.</p></div>
                    <div class="rounded-3xl border border-slate-800 bg-slate-900 p-6"><span class="text-2xl text-emerald-400">▤</span><h3 class="mt-4 font-bold">Histórico e ranking</h3><p class="mt-2 text-sm leading-6 text-slate-400">Consulte resultados e acompanhe o desempenho do grupo.</p></div>
                    <div class="rounded-3xl border border-slate-800 bg-slate-900 p-6"><span class="text-2xl text-emerald-400">↗</span><h3 class="mt-4 font-bold">Rodada ajustável</h3><p class="mt-2 text-sm leading-6 text-slate-400">Ajuste formato, tempo e modo de formação dos times.</p></div>
                </div>
            </section>

            <section class="px-5 pb-20 sm:px-8 lg:px-10">
                <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 rounded-[2rem] bg-emerald-400 px-7 py-10 text-slate-950 sm:px-10 lg:flex-row lg:items-center">
                    <div><h2 class="text-3xl font-black tracking-tight">A próxima partida pode começar melhor.</h2><p class="mt-2 text-slate-800">Organize sua galera em poucos passos.</p></div>
                    @auth
                        <a href="{{ route('dashboard') }}" class="shrink-0 rounded-2xl bg-slate-950 px-6 py-3 font-bold text-white transition hover:bg-slate-800">Abrir meu painel →</a>
                    @else
                        <a href="{{ route('register') }}" class="shrink-0 rounded-2xl bg-slate-950 px-6 py-3 font-bold text-white transition hover:bg-slate-800">Criar minha conta →</a>
                    @endauth
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-800 px-5 py-8 text-sm text-slate-400 sm:px-8 lg:px-10">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4"><span class="font-bold text-slate-200">Roda<span class="text-emerald-400">Quadra</span></span><span>Mais jogo para todo mundo.</span></div>
        </footer>
    </div>
</body>
</html>
