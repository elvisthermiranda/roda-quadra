<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · RodaQuadra</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-950 px-4 py-8 font-sans text-slate-100">
    <main class="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900 p-7 shadow-2xl sm:p-9">
        <a href="{{ route('home') }}" class="text-xs font-bold uppercase tracking-[0.25em] text-emerald-400 hover:text-emerald-300">RodaQuadra</a>
        <h1 class="mt-2 text-3xl font-black">Seu jogo, sua fila.</h1>
        <p class="mt-2 text-sm text-slate-400">Entre para organizar seus encontros e manter seus jogadores separados dos demais grupos.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-7 flex flex-col gap-4">
            @csrf
            <label class="text-sm font-medium">E-mail
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
            </label>
            @error('email') <p role="alert" class="text-sm text-rose-300">{{ $message }}</p> @enderror
            <label class="text-sm font-medium">Senha
                <input type="password" name="password" required autocomplete="current-password" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
            </label>
            @error('password') <p role="alert" class="text-sm text-rose-300">{{ $message }}</p> @enderror
            <button class="mt-2 rounded-xl bg-emerald-500 px-4 py-3 font-bold text-slate-950 hover:bg-emerald-400">Entrar</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">Ainda não tem conta? <a href="{{ route('register') }}" class="font-semibold text-emerald-300 hover:underline">Criar minha conta</a></p>
    </main>
</body>
</html>
