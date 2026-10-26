@extends('layouts.admin')

@section('title', $aggregator->legal_name)

@section('content')

@include('admin.partials.tabs', ['active' => $env])

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' XOF';
    $smColors = [
        'ACTIVE'    => 'bg-green-100 text-green-700',
        'PENDING'   => 'bg-yellow-100 text-yellow-700',
        'SUSPENDED' => 'bg-red-100 text-red-700',
        'CLOSED'    => 'bg-gray-100 text-gray-500',
    ];
@endphp

<a href="{{ route('admin.aggregators.index', $env) }}" class="text-xs text-gray-400 hover:text-gray-700">
    ← {{ $env === 'production' ? 'Agrégateurs production' : 'Agrégateurs sandbox' }}
</a>

<div class="flex items-center justify-between mt-3 mb-6">
    <div>
        <h1 class="text-lg font-bold">{{ $aggregator->legal_name }}</h1>
        <p class="text-sm text-gray-400">{{ $aggregator->email }} · {{ $aggregator->phone ?: 'pas de téléphone' }}</p>
    </div>
    <span class="text-xs px-3 py-1 rounded-full font-medium {{ $env === 'production' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
        {{ $env === 'production' ? 'Production' : 'Sandbox' }} · {{ $aggregator->status }}
    </span>
</div>

@if(session('credentials'))
    @php $c = session('credentials'); @endphp
    <div class="mb-6 p-4 rounded-lg bg-yellow-50 border border-yellow-300 text-sm">
        <p class="font-semibold text-yellow-900 mb-2">À transmettre à l'agrégateur (affichés une seule fois)</p>
        <p>Email : <code>{{ $c['email'] }}</code></p>
        <p>Mot de passe : <code>{{ $c['password'] }}</code></p>
        @if(!empty($c['verification_url']))
            <p class="mt-1">Lien de vérification (valable {{ $c['verification_hours'] ?? 72 }} h) :</p>
            <code class="block break-all text-xs">{{ $c['verification_url'] }}</code>
        @endif
    </div>
@endif

@if($env === 'sandbox' && empty($aggregator->email_verified_at))
    <div class="mb-6 p-4 rounded-lg bg-orange-50 border border-orange-200 flex items-center justify-between gap-4">
        <p class="text-sm text-orange-900">
            <strong>Email non vérifié.</strong> L'agrégateur ne peut pas encore se connecter au portail.
            Si son lien a expiré ou si l'email est perdu, vous pouvez renvoyer ses accès
            (un nouveau mot de passe est généré, l'ancien cesse de fonctionner).
        </p>
        <form method="POST" action="{{ route('admin.aggregators.resendAccess', $aggregator->id) }}"
              onsubmit="return confirm('Générer un nouveau mot de passe et renvoyer les accès ?');">
            @csrf
            <button class="px-4 py-2 rounded-lg text-sm font-medium text-white whitespace-nowrap"
                    style="background-color:#b13a7e;">
                Renvoyer les accès
            </button>
        </form>
    </div>
@endif

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    @foreach([
        ['Solde wallet',  $money($balance)],
        ['Collecté',      $money($collected)],
        ['Décaissé',      $money($disbursed)],
        ['Volume total',  $money($volume)],
    ] as [$label, $value])
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500">{{ $label }}</p>
            <p class="text-lg font-bold mt-1">{{ $value }}</p>
        </div>
    @endforeach
</div>

{{-- Applications --}}
<div class="bg-white rounded-xl border border-gray-200 p-6 mb-6 overflow-x-auto">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Applications ({{ $applications->count() }})</h2>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                <th class="py-2 font-medium">Nom</th>
                <th class="py-2 font-medium">client_id</th>
                <th class="py-2 font-medium">Active</th>
                <th class="py-2 font-medium">Créée le</th>
            </tr>
        </thead>
        <tbody>
            @forelse($applications as $app)
                <tr class="border-b border-gray-50 last:border-0">
                    <td class="py-2">{{ $app->name }}</td>
                    <td class="py-2 font-mono text-xs text-gray-600">{{ $app->client_id }}</td>
                    <td class="py-2">{{ $app->is_active ? 'Oui' : 'Non' }}</td>
                    <td class="py-2 text-gray-500">{{ \Carbon\Carbon::parse($app->created_at)->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-6 text-center text-gray-400">Aucune application.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Sous-marchands --}}
<div class="bg-white rounded-xl border border-gray-200 p-6 overflow-x-auto">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Sous-marchands ({{ $sub_merchants->count() }})</h2>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                <th class="py-2 font-medium">Sous-marchand</th>
                <th class="py-2 font-medium">Application</th>
                <th class="py-2 font-medium">Statut</th>
                <th class="py-2 font-medium text-right">Collecté</th>
                <th class="py-2 font-medium text-right">Décaissé</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sub_merchants as $s)
                <tr class="border-b border-gray-50 last:border-0">
                    <td class="py-2">
                        <p class="font-medium">{{ $s['legal_name'] }}</p>
                        <p class="text-xs text-gray-400">{{ $s['business_type'] }} · {{ $s['external_reference'] }}</p>
                    </td>
                    <td class="py-2 text-gray-600">{{ $s['application'] }}</td>
                    <td class="py-2">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $smColors[$s['status']] ?? 'bg-gray-100 text-gray-500' }}">
                            {{ $s['status'] }}
                        </span>
                    </td>
                    <td class="py-2 text-right whitespace-nowrap">{{ $money($s['collected']) }}</td>
                    <td class="py-2 text-right whitespace-nowrap">{{ $money($s['disbursed']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-gray-400">Aucun sous-marchand.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
