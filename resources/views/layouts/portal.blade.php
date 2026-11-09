<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portail Many')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Remplacez cette ligne par vos assets compilés (@vite) si vous préférez : aucune configuration Tailwind n'est requise --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        .tabular { font-variant-numeric: tabular-nums; }
    </style>
</head>

@php
    $__login = auth('aggregator')->user();
    $__ctx   = app(\App\Domain\Auth\Services\PortalEnvironmentService::class)->resolve($__login);
    $__prod  = $__ctx['env'] === 'production';

    $__nav = [
        ['portal.dashboard',          'Tableau de bord', 'home', request()->routeIs('portal.dashboard')],
        ['portal.applications.index', 'Applications',    'cube', request()->routeIs('portal.applications.*') || request()->routeIs('portal.sub-merchants.*')],
        ['portal.transactions.index', 'Transactions',    'swap', request()->routeIs('portal.transactions.*')],
        ['portal.account.password',   'Mot de passe',    'key',  request()->routeIs('portal.account.*')],
    ];
@endphp

<body class="h-full bg-slate-50 text-slate-900">

{{-- Fond sombre du menu mobile --}}
<div id="overlay" class="fixed inset-0 z-30 bg-black/50 hidden lg:hidden" onclick="toggleSidebar()"></div>

{{-- ───────── Menu latéral ───────── --}}
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full lg:translate-x-0 transition-transform duration-200
              bg-neutral-950 text-neutral-300 flex flex-col">

    <div class="h-16 flex items-center gap-3 px-6 border-b border-white/10">
        <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-[#d4569b] to-[#8f2b63] flex items-center justify-center text-white font-bold">M</div>
        <div>
            <p class="text-white font-semibold leading-none">Many</p>
            <p class="text-[11px] text-neutral-500 mt-1">Portail agrégateur</p>
        </div>
    </div>

    <nav class="flex-1 px-3 py-5 space-y-1">
        @foreach($__nav as [$route, $label, $icon, $active])
            <a href="{{ route($route) }}"
               class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                      {{ $active ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }}">
                <span class="{{ $active ? 'text-[#e46aae]' : 'text-neutral-500 group-hover:text-neutral-300' }}">
                    @include('portal.partials.icon', ['name' => $icon])
                </span>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="p-4 border-t border-white/10">
        <div class="flex items-center gap-3">
            <div class="h-9 w-9 shrink-0 rounded-full bg-[#b13a7e] flex items-center justify-center text-white text-sm font-semibold">
                {{ strtoupper(mb_substr($__login->legal_name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <p class="text-sm text-white font-medium truncate">{{ $__login->legal_name }}</p>
                <p class="text-xs text-neutral-500 truncate">{{ $__login->email }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('portal.logout') }}" class="mt-3">
            @csrf
            <button class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-neutral-400 hover:bg-white/5 hover:text-white">
                @include('portal.partials.icon', ['name' => 'logout', 'class' => 'w-4 h-4'])
                Déconnexion
            </button>
        </form>
    </div>
</aside>

{{-- ───────── Zone principale ───────── --}}
<div class="lg:pl-64 min-h-full flex flex-col">

    @unless($__prod)
        <div class="bg-amber-400/90 text-amber-950 text-xs font-medium text-center py-1.5 px-4">
            Mode test : les données et les montants sont fictifs. Aucune opération réelle n'est effectuée.
        </div>
    @endunless

    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur border-b border-slate-200">
        <div class="h-16 px-4 sm:px-8 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" class="lg:hidden p-2 -ml-2 text-slate-500" onclick="toggleSidebar()" aria-label="Menu">
                    @include('portal.partials.icon', ['name' => 'menu', 'class' => 'w-6 h-6'])
                </button>
                <h1 class="text-base font-semibold truncate">@yield('page_title', 'Portail')</h1>
            </div>

            <div class="flex items-center gap-3">
                @if($__ctx['has_production'])
                    <form method="POST" action="{{ route('portal.dashboard.environment') }}"
                          class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-semibold">
                        @csrf
                        <button name="env" value="sandbox"
                                class="px-3 py-1.5 rounded-lg transition {{ !$__prod ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-800' }}">
                            Test
                        </button>
                        <button name="env" value="production"
                                class="px-3 py-1.5 rounded-lg transition {{ $__prod ? 'bg-[#b13a7e] shadow text-white' : 'text-slate-500 hover:text-slate-800' }}">
                            Production
                        </button>
                    </form>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Mode test
                    </span>
                @endif
            </div>
        </div>
    </header>

    <main class="flex-1 px-4 sm:px-8 py-8 w-full max-w-7xl mx-auto">
        @if(session('success'))
            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                @include('portal.partials.icon', ['name' => 'check', 'class' => 'w-5 h-5 mt-0.5 shrink-0'])
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="px-8 py-6 text-center text-xs text-slate-400">Many Paiement · Portail agrégateur</footer>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('-translate-x-full');
        document.getElementById('overlay').classList.toggle('hidden');
    }

    function copyText(text, button) {
        const done = () => {
            const old = button.textContent;
            button.textContent = 'Copié ✓';
            setTimeout(() => button.textContent = old, 1500);
        };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            const t = document.createElement('textarea');
            t.value = text; document.body.appendChild(t); t.select();
            document.execCommand('copy'); document.body.removeChild(t); done();
        }
    }
</script>
@stack('scripts')
</body>
</html>
