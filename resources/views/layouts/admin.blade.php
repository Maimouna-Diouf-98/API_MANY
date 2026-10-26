<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Many</title>

    {{-- Remplacez cette ligne par les mêmes assets CSS que layouts/portal.blade.php --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">

<header class="bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
        <a href="{{ route('admin.dashboard') }}" class="font-bold" style="color:#b13a7e;">
            Many · Administration
        </a>

        <div class="flex items-center space-x-5">
            {{-- Cloche de notifications --}}
            <form method="POST" action="{{ route('admin.notifications.readAll') }}">
                @csrf
                <button type="submit" class="relative" title="Marquer tout comme lu">
                    <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9"/>
                    </svg>
                    <span id="bell-badge"
                          class="{{ ($unreadCount ?? 0) > 0 ? '' : 'hidden' }}
                                 absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full
                                 bg-red-600 text-white text-[10px] font-bold
                                 flex items-center justify-center">
                        {{ $unreadCount ?? 0 }}
                    </span>
                </button>
            </form>

            <span class="text-sm text-gray-500">{{ auth('admin')->user()->name }}</span>

            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="text-sm text-gray-500 hover:text-gray-800">Déconnexion</button>
            </form>
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-4 py-8">
    @if(session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    @yield('content')
</main>

<script>
    // Mise à jour du badge toutes les 30 secondes
    setInterval(() => {
        fetch("{{ route('admin.notifications.count') }}", { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : null)
            .then(d => {
                if (!d) return;
                const badge = document.getElementById('bell-badge');
                badge.textContent = d.unread;
                badge.classList.toggle('hidden', d.unread === 0);
            })
            .catch(() => {});
    }, 30000);
</script>
</body>
</html>
