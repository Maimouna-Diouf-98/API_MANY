<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Many — Portail Développeur')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --color-primary: #b13a7e;
            --color-primary-light: #f9eef5;
            --color-primary-dark: #8e2e64;
        }
        .bg-primary { background-color: var(--color-primary); }
        .text-primary { color: var(--color-primary); }
        .border-primary { border-color: var(--color-primary); }
        .bg-primary-light { background-color: var(--color-primary-light); }
        .hover\:bg-primary-dark:hover { background-color: var(--color-primary-dark); }
        .nav-active { background-color: var(--color-primary-light); color: var(--color-primary); }
        .nav-inactive { color: #4b5563; }
        .nav-inactive:hover { background-color: #f9fafb; }
    </style>
</head>
<body class="bg-gray-50 font-sans">

    <div class="flex h-screen">
        <aside class="w-64 bg-white border-r border-gray-200 flex flex-col">

            {{-- Logo --}}
            <div class="h-16 flex items-center px-6 border-b border-gray-200">
                <span class="text-xl font-bold text-primary">Many</span>
                <span class="ml-2 text-xs px-2 py-0.5 rounded-full font-medium"
                      style="background-color:#f9eef5; color:#b13a7e;">
                    Portail
                </span>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 px-4 py-6 space-y-1">

                <a href="{{ route('portal.dashboard') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm font-medium
                   {{ request()->routeIs('portal.dashboard') ? 'nav-active' : 'nav-inactive' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>

              {{-- Remplacez le lien sous-marchands par --}}
<a href="{{ route('portal.applications.index') }}"
   class="flex items-center px-3 py-2 rounded-lg text-sm font-medium
   {{ request()->routeIs('portal.applications.*') ||
      request()->routeIs('portal.sub-merchants.*')
      ? 'nav-active' : 'nav-inactive' }}">
    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
    </svg>
    Applications & Marchands
</a>

<a href="{{ route('portal.transactions.index') }}"
   class="flex items-center px-3 py-2 rounded-lg text-sm font-medium
   {{ request()->routeIs('portal.transactions.*') ? 'nav-active' : 'nav-inactive' }}">
    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
    </svg>
    Transactions
</a>

            </nav>

            {{-- Profil --}}
            <div class="px-4 py-4 border-t border-gray-200">
    <div class="flex items-center">
        <div class="w-8 h-8 rounded-full flex items-center justify-center"
             style="background-color:#f9eef5;">
            <span class="text-sm font-bold text-primary">
                {{ strtoupper(substr(auth('aggregator')->user()->legal_name ?? 'A', 0, 1)) }}
            </span>
        </div>
        <div class="ml-3">
            <p class="text-sm font-medium text-gray-800">
                {{ auth('aggregator')->user()->legal_name ?? 'Agrégateur' }}
            </p>
            <p class="text-xs text-gray-400">
                {{ ucfirst(strtolower(auth('aggregator')->user()->status ?? 'sandbox')) }}
            </p>
        </div>
        <form method="POST" action="{{ route('portal.logout') }}" class="ml-auto">
            @csrf
            <button type="submit" class="text-gray-400 hover:text-red-500 transition">
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
                    @yield('page_title', 'Dashboard')
                </h1>
                <div class="ml-auto">
                    <span class="text-xs px-3 py-1 rounded-full font-medium"
                          style="background-color:#f9eef5; color:#b13a7e;">
                        Sandbox
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