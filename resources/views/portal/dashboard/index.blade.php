@extends('layouts.portal')

@section('title', 'Tableau de bord — Many')
@section('page_title', 'Tableau de bord')

@section('content')

@php
    $isProd = $env === 'production';
    $money  = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $c      = $activity['counts'];
    $rate   = $activity['success_rate'];
    $tr     = $activity['trends'];

    $typeMeta = [
        'COLLECTION'   => ['Collection',        'bg-emerald-50 text-emerald-600', 'arrow-d'],
        'DISBURSEMENT' => ['Décaissement',      'bg-blue-50 text-blue-600',       'arrow-u'],
        'BULK'         => ['Paiement de masse', 'bg-violet-50 text-violet-600',   'arrow-u'],
    ];
    $typeRoute = ['COLLECTION' => 'collections', 'DISBURSEMENT' => 'disbursements', 'BULK' => 'bulks'];
@endphp

{{-- ───────── En-tête ───────── --}}
<div class="flex flex-wrap items-end justify-between gap-4 mb-8">
    <div>
        <p class="text-sm text-slate-500">{{ $isProd ? 'Compte de production' : 'Environnement de test' }}</p>
        <h2 class="text-2xl font-bold tracking-tight">Bonjour, {{ $aggregator->legal_name }}</h2>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('portal.applications.index') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
            @include('portal.partials.icon', ['name' => 'cube', 'class' => 'w-4 h-4']) Applications
        </a>
        <a href="{{ route('portal.transactions.index') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-[#b13a7e] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#9a3069]">
            @include('portal.partials.icon', ['name' => 'swap', 'class' => 'w-4 h-4']) Transactions
        </a>
    </div>
</div>

@if($isProd && $envAggregator->status !== 'PRODUCTION_ACTIVE')
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Votre compte de production est <strong>{{ $envAggregator->status }}</strong>.
        Contactez l'équipe Many pour plus d'informations.
    </div>
@endif

{{-- ───────── Mode test : progression + demande de production ───────── --}}
@unless($isProd)
    @php
        $steps = [
            ['Compte créé',       true],
            ['Tests réalisés',    $c['collections'] > 0],
            ['Demande envoyée',   (bool) $productionRequest],
            ['Production active', $hasProduction],
        ];
    @endphp

    <div class="grid lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
            <h3 class="font-semibold mb-6">Votre progression</h3>
            <div class="flex items-start">
                @foreach($steps as $i => [$label, $done])
                    <div class="flex flex-col items-center text-center w-24 shrink-0">
                        <div class="h-9 w-9 rounded-full flex items-center justify-center text-sm font-bold
                                    {{ $done ? 'bg-[#b13a7e] text-white' : 'bg-slate-100 text-slate-400' }}">
                            @if($done) @include('portal.partials.icon', ['name' => 'check', 'class' => 'w-4 h-4']) @else {{ $i + 1 }} @endif
                        </div>
                        <p class="text-xs mt-2 {{ $done ? 'text-slate-900 font-medium' : 'text-slate-400' }}">{{ $label }}</p>
                    </div>
                    @if(!$loop->last)
                        <div class="flex-1 h-0.5 mt-[18px] {{ $done ? 'bg-[#b13a7e]/40' : 'bg-slate-200' }}"></div>
                    @endif
                @endforeach
            </div>
        </div>
        <div class="lg:col-span-1">@include('portal.partials.production-request')</div>
    </div>
@endunless

{{-- ───────── Indicateurs ───────── --}}
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

    <div class="rounded-2xl bg-gradient-to-br from-neutral-900 via-neutral-900 to-[#6b1f48] p-6 text-white shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-sm text-neutral-300">Solde {{ $isProd ? 'disponible' : 'fictif' }}</p>
            @include('portal.partials.icon', ['name' => 'wallet', 'class' => 'w-5 h-5 text-[#e46aae]'])
        </div>
        <p class="mt-4 text-3xl font-bold tabular">{{ $money($data['balance']) }}</p>
        <p class="text-xs text-neutral-400 mt-1">XOF</p>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-500">Collecté (7 jours)</p>
            <span class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                @include('portal.partials.icon', ['name' => 'arrow-d', 'class' => 'w-4 h-4'])
            </span>
        </div>
        <p class="mt-4 text-2xl font-bold tabular">{{ $money($activity['week']['collected']) }} <span class="text-sm font-medium text-slate-400">XOF</span></p>
        <p class="text-xs mt-1">
            @if($tr['collected'] === null)
                <span class="text-slate-400">Pas de période précédente</span>
            @else
                <span class="font-semibold {{ $tr['collected'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $tr['collected'] >= 0 ? '▲' : '▼' }} {{ abs($tr['collected']) }} %
                </span>
                <span class="text-slate-400">vs 7 jours précédents</span>
            @endif
        </p>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-500">Décaissé (7 jours)</p>
            <span class="h-8 w-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                @include('portal.partials.icon', ['name' => 'arrow-u', 'class' => 'w-4 h-4'])
            </span>
        </div>
        <p class="mt-4 text-2xl font-bold tabular">{{ $money($activity['week']['disbursed']) }} <span class="text-sm font-medium text-slate-400">XOF</span></p>
        <p class="text-xs mt-1">
            @if($tr['disbursed'] === null)
                <span class="text-slate-400">Pas de période précédente</span>
            @else
                <span class="font-semibold {{ $tr['disbursed'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $tr['disbursed'] >= 0 ? '▲' : '▼' }} {{ abs($tr['disbursed']) }} %
                </span>
                <span class="text-slate-400">vs 7 jours précédents</span>
            @endif
        </p>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-500">Taux de réussite</p>
            <span class="h-8 w-8 rounded-lg bg-[#b13a7e]/10 text-[#b13a7e] flex items-center justify-center">
                @include('portal.partials.icon', ['name' => 'check', 'class' => 'w-4 h-4'])
            </span>
        </div>
        <p class="mt-4 text-2xl font-bold tabular">{{ $rate === null ? '—' : $rate . ' %' }}</p>
        <div class="mt-3 h-1.5 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full bg-[#b13a7e]" style="width: {{ $rate ?? 0 }}%"></div>
        </div>
        <p class="text-xs text-slate-400 mt-2">{{ $c['collections'] }} réussie(s) sur {{ $c['collections_total'] }} collection(s)</p>
    </div>
</div>

{{-- ───────── Totaux cumulés ───────── --}}
<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="rounded-2xl bg-white border border-slate-200 p-5">
        <p class="text-xs text-slate-500">Total collecté</p>
        <p class="text-lg font-bold mt-1 tabular">{{ $money($data['collected']) }} XOF</p>
    </div>
    <div class="rounded-2xl bg-white border border-slate-200 p-5">
        <p class="text-xs text-slate-500">Total décaissé</p>
        <p class="text-lg font-bold mt-1 tabular">{{ $money($data['disbursed']) }} XOF</p>
    </div>
    <div class="rounded-2xl bg-white border border-slate-200 p-5">
        <p class="text-xs text-slate-500">Marchands actifs · Applications</p>
        <p class="text-lg font-bold mt-1">{{ $activeSubMerchants }} · {{ $data['applications']->count() }}</p>
    </div>
</div>

{{-- ───────── Graphiques ───────── --}}
<div class="grid lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold">Activité des 7 derniers jours</h3>
            <div class="flex items-center gap-4 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#b13a7e]"></span>Collecté</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-blue-500"></span>Décaissé</span>
            </div>
        </div>
        <div class="h-72"><canvas id="activityChart"></canvas></div>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <h3 class="font-semibold mb-4">Statut des collections</h3>
        @if($c['collections_total'] > 0)
            <div class="h-52 relative">
                <canvas id="statusChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <p class="text-2xl font-bold">{{ $c['collections_total'] }}</p>
                    <p class="text-xs text-slate-400">collections</p>
                </div>
            </div>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach([['SUCCESS','Réussies','bg-emerald-500'],['PENDING','En attente','bg-amber-500'],['FAILED','Échouées','bg-red-500'],['REFUNDED','Remboursées','bg-slate-400']] as [$k,$l,$col])
                    <li class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-2 text-slate-600"><span class="h-2.5 w-2.5 rounded-full {{ $col }}"></span>{{ $l }}</span>
                        <span class="font-semibold tabular">{{ $activity['breakdown'][$k] }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="h-52 flex flex-col items-center justify-center text-center text-slate-400">
                @include('portal.partials.icon', ['name' => 'swap', 'class' => 'w-8 h-8 mb-2'])
                <p class="text-sm">Aucune collection pour l'instant.</p>
            </div>
        @endif
    </div>
</div>

{{-- ───────── Opérations récentes + applications ───────── --}}
<div class="grid lg:grid-cols-3 gap-6">

    <div class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h3 class="font-semibold">Opérations récentes</h3>
            <a href="{{ route('portal.transactions.index') }}" class="text-sm font-medium text-[#b13a7e] hover:underline">Tout voir</a>
        </div>

        @forelse($activity['recent'] as $op)
            @php [$label, $iconCls, $icon] = $typeMeta[$op['type']]; @endphp
            <a href="{{ route('portal.transactions.show', [$op['id'], 'type' => $typeRoute[$op['type']]]) }}"
               class="flex items-center justify-between gap-4 px-6 py-3.5 border-b border-slate-50 last:border-0 hover:bg-slate-50 transition">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center {{ $iconCls }}">
                        @include('portal.partials.icon', ['name' => $icon, 'class' => 'w-4 h-4'])
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium truncate">{{ $label }} <span class="font-mono text-xs text-slate-400 ml-1">{{ $op['id'] }}</span></p>
                        <p class="text-xs text-slate-400 truncate">{{ $op['label'] ?: '—' }} · {{ $op['created_at']->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 shrink-0">
                    <p class="text-sm font-semibold tabular">{{ $money($op['amount']) }} <span class="text-xs text-slate-400">XOF</span></p>
                    @include('portal.partials.badge', ['status' => $op['status']])
                </div>
            </a>
        @empty
            <div class="px-6 py-14 text-center text-slate-400">
                @include('portal.partials.icon', ['name' => 'swap', 'class' => 'w-8 h-8 mx-auto mb-2'])
                <p class="text-sm">Aucune opération pour l'instant.</p>
                <p class="text-xs mt-1">Elles apparaîtront ici dès votre premier appel à l'API.</p>
            </div>
        @endforelse
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h3 class="font-semibold">Mes applications</h3>
            <a href="{{ route('portal.applications.index') }}" class="text-sm font-medium text-[#b13a7e] hover:underline">Gérer</a>
        </div>

        @forelse($data['applications']->take(4) as $app)
            <div class="px-6 py-3.5 border-b border-slate-50 last:border-0">
                <div class="flex items-center justify-between gap-2">
                    <a href="{{ route('portal.applications.show', $app->id) }}" class="text-sm font-medium hover:underline truncate">{{ $app->name }}</a>
                    @include('portal.partials.badge', ['status' => $app->is_active ? 'ACTIVE' : 'CLOSED'])
                </div>
                <div class="mt-1.5 flex items-center justify-between gap-2">
                    <code class="text-xs text-slate-500 truncate">{{ $app->client_id }}</code>
                    <button type="button" onclick="copyText('{{ $app->client_id }}', this)"
                            class="text-xs font-medium text-[#b13a7e] hover:underline shrink-0">Copier</button>
                </div>
            </div>
        @empty
            <div class="px-6 py-10 text-center text-slate-400">
                <p class="text-sm">Aucune application.</p>
                <a href="{{ route('portal.applications.index') }}" class="inline-block mt-3 text-sm font-semibold text-[#b13a7e] hover:underline">Créer ma première application</a>
            </div>
        @endforelse
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const fmt = v => new Intl.NumberFormat('fr-FR').format(v);

    // Dégradé sous la courbe
    function gradient(ctx, rgb) {
        const g = ctx.createLinearGradient(0, 0, 0, 280);
        g.addColorStop(0, `rgba(${rgb}, 0.25)`);
        g.addColorStop(1, `rgba(${rgb}, 0)`);
        return g;
    }

    const lineCtx = document.getElementById('activityChart').getContext('2d');
    new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: @json($activity['chart']['labels']),
            datasets: [
                {
                    label: 'Collecté',
                    data: @json($activity['chart']['collections']),
                    borderColor: '#b13a7e', backgroundColor: gradient(lineCtx, '177, 58, 126'),
                    borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 5,
                },
                {
                    label: 'Décaissé',
                    data: @json($activity['chart']['disbursements']),
                    borderColor: '#3b82f6', backgroundColor: gradient(lineCtx, '59, 130, 246'),
                    borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 5,
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => ` ${c.dataset.label} : ${fmt(c.parsed.y)} XOF` } },
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: '#f1f5f9' },
                     ticks: { callback: v => fmt(v) } },
            },
        },
    });

    @if($c['collections_total'] > 0)
    new Chart(document.getElementById('statusChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Réussies', 'En attente', 'Échouées', 'Remboursées'],
            datasets: [{
                data: @json(array_values($activity['breakdown'])),
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#94a3b8'],
                borderWidth: 0,
            }],
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '74%', plugins: { legend: { display: false } } },
    });
    @endif
</script>
@endpush
