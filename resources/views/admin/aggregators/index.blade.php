@extends('layouts.admin')

@section('title', $env === 'production' ? 'Agrégateurs production' : 'Agrégateurs sandbox')

@section('content')

@include('admin.partials.tabs', ['active' => $env])

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

@if($env === 'sandbox')
    <div class="flex justify-end mb-4">
        <a href="{{ route('admin.aggregators.create') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium text-white"
           style="background-color:#b13a7e;">
            + Créer un agrégateur
        </a>
    </div>
@endif

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' XOF';
    $statusColors = [
        'PRODUCTION_ACTIVE'  => 'bg-green-100 text-green-700',
        'PRODUCTION_PENDING' => 'bg-yellow-100 text-yellow-700',
        'SANDBOX_ACTIVE'     => 'bg-yellow-100 text-yellow-700',
        'SUSPENDED'          => 'bg-red-100 text-red-700',
        'CLOSED'             => 'bg-gray-100 text-gray-500',
    ];
@endphp

@if($env === 'sandbox')
    <p class="mb-4 text-xs text-gray-400">
        Environnement de test : tous les soldes et montants sont fictifs.
    </p>
@endif

{{-- Totaux --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    @foreach([
        ['Agrégateurs',      $totals['aggregators']],
        ['Applications',     $totals['apps']],
        ['Sous-marchands',   $totals['sub_merchants']],
        ['Solde des wallets', $money($totals['balance'])],
        ['Total collecté',   $money($totals['collected'])],
        ['Total décaissé',   $money($totals['disbursed'])],
        ['Volume total',     $money($totals['volume'])],
    ] as [$label, $value])
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500">{{ $label }}</p>
            <p class="text-lg font-bold mt-1">{{ $value }}</p>
        </div>
    @endforeach
</div>

{{-- Liste --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                <th class="px-4 py-3 font-medium">Agrégateur</th>
                <th class="px-4 py-3 font-medium">Statut</th>
                <th class="px-4 py-3 font-medium text-right">Apps</th>
                <th class="px-4 py-3 font-medium text-right">Marchands</th>
                <th class="px-4 py-3 font-medium text-right">Solde</th>
                <th class="px-4 py-3 font-medium text-right">Collecté</th>
                <th class="px-4 py-3 font-medium text-right">Décaissé</th>
                <th class="px-4 py-3 font-medium text-right">Volume total</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <p class="font-medium">{{ $r['legal_name'] }}</p>
                        <p class="text-xs text-gray-400">{{ $r['email'] }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                     {{ $statusColors[$r['status']] ?? 'bg-gray-100 text-gray-500' }}">
                            {{ $r['status'] }}
                        </span>
                        @unless($r['email_verified'])
                            <span class="ml-1 text-xs px-2 py-0.5 rounded-full font-medium bg-orange-100 text-orange-700">
                                Email non vérifié
                            </span>
                        @endunless
                    </td>
                    <td class="px-4 py-3 text-right">{{ $r['apps'] }}</td>
                    <td class="px-4 py-3 text-right">{{ $r['sub_merchants'] }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $money($r['balance']) }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $money($r['collected']) }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $money($r['disbursed']) }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap font-semibold">{{ $money($r['volume']) }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.aggregators.show', [$env, $r['id']]) }}"
                           class="text-xs hover:underline" style="color:#b13a7e;">Détails →</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-10 text-center text-gray-400">Aucun agrégateur.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="mt-3 text-xs text-gray-400">
    Collecté = collections réussies · Décaissé = décaissements et paiements de masse réussis ·
    Volume total = collecté + décaissé.
</p>
@endsection
