@extends('layouts.portal')

@section('title', 'Dashboard — Many Portail')
@section('page_title', 'Dashboard')

@section('content')

{{-- Bienvenue --}}
<div class="mb-6">
    <h2 class="text-lg font-bold text-gray-900">
        Bienvenue, {{ $aggregator->legal_name }} 👋🏾
    </h2>
    <p class="text-sm text-gray-400 mt-0.5">
        Voici un aperçu de votre espace agrégateur Many.
    </p>
</div>

{{-- Cartes statistiques --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">

    {{-- Balance --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-gray-500">Balance Many</span>
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                 style="background-color:#f9eef5;">
                <svg class="w-4 h-4" style="color:#b13a7e;" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2"
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">
            {{ number_format($balance, 0, ',', ' ') }}
            <span class="text-sm font-normal text-gray-400">XOF</span>
        </p>
        <p class="text-xs text-gray-400 mt-1">Solde disponible</p>
    </div>

    {{-- Applications --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-gray-500">Applications</span>
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                 style="background-color:#f9eef5;">
                <svg class="w-4 h-4" style="color:#b13a7e;" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2"
                          d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">{{ $totalApplications }}</p>
        <p class="text-xs text-gray-400 mt-1">Applications actives</p>
    </div>

    {{-- Sous-marchands --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-gray-500">Sous-marchands</span>
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                 style="background-color:#f9eef5;">
                <svg class="w-4 h-4" style="color:#b13a7e;" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">{{ $totalSubMerchants }}</p>
        <p class="text-xs text-gray-400 mt-1">Sous-marchands actifs</p>
    </div>

    {{-- Transactions --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-gray-500">Transactions</span>
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                 style="background-color:#f9eef5;">
                <svg class="w-4 h-4" style="color:#b13a7e;" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">{{ $totalTransactions }}</p>
        <p class="text-xs text-gray-400 mt-1">Transactions réussies</p>
    </div>
{{-- Décaissements --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-medium text-gray-500">Décaissements</span>
        <div class="w-8 h-8 rounded-lg flex items-center justify-center"
             style="background-color:#eff6ff;">
            <svg class="w-4 h-4 text-blue-500" fill="none"
                 stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      stroke-width="2"
                      d="M5 10l7-7m0 0l7 7M12 3v18"/>
            </svg>
        </div>
    </div>
    <p class="text-2xl font-bold text-gray-900">
        {{ $totalDisbursements + $totalBulk }}
    </p>
    <p class="text-xs text-gray-400 mt-1">
        {{ $totalDisbursements }} unitaires · {{ $totalBulk }} en masse
    </p>
</div>

{{-- Montant décaissé --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-medium text-gray-500">Montant décaissé</span>
        <div class="w-8 h-8 rounded-lg flex items-center justify-center"
             style="background-color:#eff6ff;">
            <svg class="w-4 h-4 text-blue-500" fill="none"
                 stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
    </div>
    <p class="text-2xl font-bold text-gray-900">
        {{ number_format($totalDecaisse, 0, ',', ' ') }}
        <span class="text-sm font-normal text-gray-400">XOF</span>
    </p>
    <p class="text-xs text-gray-400 mt-1">
        {{ number_format($totalDisbursed, 0, ',', ' ') }} XOF unitaires
        · {{ number_format($totalBulkAmount, 0, ',', ' ') }} XOF en masse
    </p>
</div>

</div>

{{-- Volume total --}}
<div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
    <div class="grid grid-cols-2 gap-6">
        <div>
            <p class="text-xs text-gray-400 mb-1">Volume total traité</p>
            <p class="text-xl font-bold text-gray-900">
                {{ number_format($totalVolume, 0, ',', ' ') }} XOF
            </p>
            <p class="text-xs text-gray-400 mt-1">Montant brut cumulé</p>
        </div>
        <div>
            <p class="text-xs text-gray-400 mb-1">Net reçu total</p>
            <p class="text-xl font-bold" style="color:#b13a7e;">
                {{ number_format($totalVolume - ($totalVolume * 0.01), 0, ',', ' ') }} XOF
            </p>
            <p class="text-xs text-gray-400 mt-1">Après déduction frais Many (1%)</p>
        </div>
        
    </div>
</div>

{{-- Transactions récentes --}}
<div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-700">Transactions récentes</h2>
        <a href="{{ route('portal.transactions.index') }}"
           class="text-sm hover:underline" style="color:#b13a7e;">
            Voir toutes →
        </a>
    </div>
    @if($recentTransactions->isEmpty())
        <p class="text-sm text-gray-400 text-center py-6">
            Aucune transaction pour l'instant.
        </p>
    @else
        <div class="space-y-3">
            @foreach($recentTransactions as $tx)
                <div class="flex items-center justify-between py-2
                            border-b border-gray-50 last:border-0">
                    <div>
                        <div class="flex items-center space-x-2">
                            <p class="text-xs font-mono text-gray-600">
                                {{ $tx['id'] }}
                            </p>
                            @if($tx['type'] === 'COLLECTION')
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-green-100 text-green-700">↓ Collection</span>
                            @elseif($tx['type'] === 'DISBURSEMENT')
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-red-800">↑ Décaissement</span>
                            @else
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-purple-100 text-orange-800">↑↑ Masse</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $tx['reference'] ?? '—' }} ·
                            {{ \Carbon\Carbon::parse($tx['created_at'])->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">
                                {{ $tx['type'] === 'COLLECTION' ? '+' : '-' }}
                                {{ number_format($tx['amount'], 0, ',', ' ') }} XOF
                            </p>
                            <p class="text-xs" style="color:#b13a7e;">
                                Net : {{ number_format($tx['net'], 0, ',', ' ') }} XOF
                            </p>
                        </div>
                        @php
                            $colors = [
                                'PENDING'    => 'bg-yellow-100 text-yellow-700',
                                'PROCESSING' => 'bg-blue-100 text-blue-700',
                                'SUCCESS'    => 'bg-green-100 text-green-700',
                                'COMPLETED'  => 'bg-green-100 text-green-700',
                                'FAILED'     => 'bg-red-100 text-red-700',
                                'REFUNDED'   => 'bg-gray-100 text-gray-500',
                            ];
                        @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium
                            {{ $colors[$tx['status']] ?? 'bg-gray-100 text-gray-500' }}">
                            {{ $tx['status'] }}
                        </span>
                        <a href="{{ route('portal.transactions.show', $tx['id']) }}"
                           class="text-xs hover:underline" style="color:#b13a7e;">
                            Voir
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Statut KYB --}}
<div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">
        Statut de vérification KYB
    </h2>
    <div class="flex items-center">
        @php
            $kyb       = $aggregator->kybVerification;
            $kybStatus = $kyb?->status ?? 'NON_SOUMIS';
            $kybColors = [
                'NON_SOUMIS'   => 'bg-gray-300',
                'PENDING'      => 'bg-yellow-400',
                'UNDER_REVIEW' => 'bg-blue-400',
                'APPROVED'     => 'bg-green-400',
                'REJECTED'     => 'bg-red-400',
                'EXPIRED'      => 'bg-orange-400',
            ];
            $kybLabels = [
                'NON_SOUMIS'   => 'Documents non soumis',
                'PENDING'      => 'En attente de traitement',
                'UNDER_REVIEW' => 'En cours de vérification',
                'APPROVED'     => 'KYB approuvé',
                'REJECTED'     => 'KYB rejeté',
                'EXPIRED'      => 'Documents expirés',
            ];
        @endphp
        <div class="w-3 h-3 rounded-full {{ $kybColors[$kybStatus] }} mr-3"></div>
        <div>
            <p class="text-sm font-medium text-gray-800">
                {{ $kybLabels[$kybStatus] }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">
                @if($kybStatus === 'NON_SOUMIS')
                    Soumettez vos documents pour accéder à la production.
                @elseif($kybStatus === 'APPROVED')
                    Votre compte est éligible à la production.
                @else
                    Many examine votre dossier.
                @endif
            </p>
        </div>
        @if(in_array($kybStatus, ['NON_SOUMIS', 'REJECTED']))
            <a href="#" class="ml-auto text-sm font-medium hover:underline"
               style="color:#b13a7e;">
                Soumettre les documents →
            </a>
        @endif
    </div>
</div>

{{-- Progression --}}
{{-- <div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-6">Votre progression</h2>
    <div class="flex items-center">
        @php
            $steps = [
                ['label' => 'Compte créé',       'done' => true],
                ['label' => 'KYB soumis',        'done' => in_array($kybStatus,
                    ['PENDING', 'UNDER_REVIEW', 'APPROVED'])],
                ['label' => 'KYB validé',        'done' => $kybStatus === 'APPROVED'],
                ['label' => 'Production active', 'done' => $aggregator->production_enabled],
            ];
        @endphp
        @foreach($steps as $i => $step)
            <div class="flex flex-col items-center flex-1">
                <div class="w-8 h-8 rounded-full flex items-center justify-center
                     text-xs font-bold
                     {{ $step['done'] ? 'text-white' : 'bg-gray-100 text-gray-400' }}"
                     style="{{ $step['done'] ? 'background-color:#b13a7e;' : '' }}">
                    @if($step['done'])
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                  clip-rule="evenodd"/>
                        </svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-2 text-center">
                    {{ $step['label'] }}
                </p>
            </div>
            @if(!$loop->last)
                <div class="flex-1 h-px bg-gray-200 mb-4"></div>
            @endif
        @endforeach
    </div>
</div> --}}

@endsection