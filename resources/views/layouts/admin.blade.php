<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Many — Administration')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --color-primary: #b13a7e;
            --color-primary-dark: #8e2e64;
        }
        .text-primary { color: var(--color-primary); }
        .bg-primary { background-color: var(--color-primary); }
        .nav-active { background-color: #b13a7e; color: #ffffff; }
        .nav-inactive { color: #9ca3af; }
        .nav-inactive:hover { background-color: #1f2937; color: #ffffff; }
    </style>
</head>
<body class="bg-gray-50 font-sans">

    <div class="flex h-screen">
        <aside class="w-64 bg-black flex flex-col">

            {{-- Logo --}}
            <div class="h-16 flex items-center px-6 border-b border-gray-800">
                <span class="text-xl font-bold text-white">Many</span>
                <span class="ml-2 text-xs px-2 py-0.5 rounded-full font-medium bg-primary text-white">
                    Admin
                </span>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 px-4 py-6 space-y-1">

                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition
                   {{ request()->routeIs('admin.dashboard') ? 'nav-active' : 'nav-inactive' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Vue globale
                </a>

                <a href="{{ route('admin.aggregators.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition
                   {{ request()->routeIs('admin.aggregators.*') ? 'nav-active' : 'nav-inactive' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Agrégateurs
                </a>

                <a href="{{ route('admin.kyb.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition
                   {{ request()->routeIs('admin.kyb.*') ? 'nav-active' : 'nav-inactive' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Vérification KYB
                </a>

            </nav>

            {{-- Admin connecté --}}
            <div class="px-4 py-4 border-t border-gray-800">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center">
                        <span class="text-white text-sm font-bold">M</span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-white">Many Admin</p>
                        <p class="text-xs text-gray-400">Opérateur</p>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}" class="ml-auto">
                        @csrf
                        <button type="submit" class="text-gray-400 hover:text-primary transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

        </aside>

        {{-- Contenu principal --}}
        <div class="flex-1 flex flex-col overflow-hidden">

            <header class="h-16 bg-white border-b border-gray-200 flex items-center px-6">
                <h1 class="text-lg font-semibold text-gray-900">
                    @yield('page_title', 'Vue globale')
                </h1>
                <div class="ml-auto">
                    <span class="text-xs px-3 py-1 rounded-full font-medium bg-primary text-white">
                        Administration
                    </span>
                </div>
            </header>

            <div class="px-6 pt-4">
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
                        {{ session('error') }}
                    </div>
                @endif
            </div>

            <main class="flex-1 overflow-y-auto px-6 py-4">
                @yield('content')
            </main>

        </div>
    </div>

</body>
</html>