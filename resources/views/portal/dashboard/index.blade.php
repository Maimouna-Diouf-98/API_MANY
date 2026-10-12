@extends('layouts.portal')

@section('title', 'Dashboard — Many Portail')
@section('page_title', 'Dashboard')

@section('content')

{{-- Bienvenue + statut --}}
<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-lg font-bold text-gray-900">
            Bonjour, {{ $aggregator->legal_name }} 👋🏾
        </h2>
        <p class="text-sm text-gray-400 mt-0.5">
            Voici un aperçu de votre espace agrégateur Many.
        </p>
    </div>
    <div class="flex items-center space-x-3">
        {{-- Statut --}}
        @if($aggregator->status === 'SANDBOX_ACTIVE')
            <span class="text-xs px-3 py-1 rounded-full font-medium
                         bg-yellow-100 text-yellow-700">
                🧪 Sandbox
            </span>
        @else
            <span class="text-xs px-3 py-1 rounded-full font-medium
                         bg-green-100 text-green-700">
                ✅ Production
            </span>
        @endif
    </div>
</div>

{{-- Progression Sandbox → Production --}}
@if($aggregator->status === 'SANDBOX_ACTIVE')
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
        <h3 class="text-sm font-semibold text-gray-700 mb-6">
            Votre progression
        </h3>
        <div class="flex items-center">
            @php
                $hasPendingRequest = $productionRequest && $productionRequest->status === 'PENDING';
                $steps = [
                    ['label' => 'Compte créé',       'done' => true],
                    ['label' => 'Tests sandbox',      'done' => $totalCollections > 0],
                    ['label' => 'Demande envoyée',    'done' => (bool) $productionRequest],
                    ['label' => 'Production active',  'done' => false],
                ];
            @endphp
            @foreach($steps as $i => $step)
                <div class="flex flex-col items-center flex-1">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center
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
                    <div class="flex-1 h-px mb-5
                        {{ $step['done'] ? 'bg-pink-300' : 'bg-gray-200' }}">
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Bouton demande production --}}
        @if(!$productionRequest)
            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-500 mb-4">
                    Vos tests sont terminés ? Demandez l'accès à la production.
                </p>
                <button onclick="document.getElementById('modal-production').classList.remove('hidden')"
                        class="px-6 py-2.5 rounded-lg text-sm font-medium text-white"
                        style="background-color:#b13a7e;">
                    🚀 Demander l'accès production
                </button>
            </div>
        @elseif($productionRequest->status === 'PENDING')
            <div class="mt-6 pt-6 border-t border-gray-100">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                    <p class="text-sm font-medium text-yellow-800">
                        ⏳ Demande en cours de traitement
                    </p>
                    <p class="text-xs text-yellow-600 mt-1">
                        L'équipe Many examine votre dossier et vous contactera
                        par email sous 24-48h.
                    </p>
                </div>
            </div>
        @elseif($productionRequest->status === 'APPROVED')
            <div class="mt-6 pt-6 border-t border-gray-100">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <p class="text-sm font-medium text-green-800">
                        ✅ Accès production approuvé !
                    </p>
                    <p class="text-xs text-green-600 mt-1">
                        Vérifiez votre email pour vos credentials production.
                    </p>
                </div>
            </div>
        @endif
    </div>
@endif

{{-- Cartes statistiques --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">

    {{-- Balance --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 col-span-2 md:col-span-1">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-medium text-gray-500">Balance Many</span>
            <div class="w-7 h-7 rounded-lg flex items-center justify-center"
                 style="background-color:#f9eef5;">
                <svg class="w-3.5 h-3.5" style="color:#b13a7e;" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">
            {{ number_format($balance, 0, ',', ' ') }}
        </p>
        <p class="text-xs text-gray-400 mt-1">XOF disponible</p>
    </div>

    {{-- Collections --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-medium text-gray-500">Collections</span>
            <div class="w-7 h-7 rounded-lg flex items-center justify-center
                        bg-green-50">
                <svg class="w-3.5 h-3.5 text-green-500" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">{{ $totalCollections }}</p>
        <p class="text-xs text-gray-400 mt-1">
            {{ number_format($totalVolume, 0, ',', ' ') }} XOF
        </p>
    </div>

    {{-- Décaissements --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-medium text-gray-500">Décaissements</span>
            <div class="w-7 h-7 rounded-lg flex items-center justify-center
                        bg-blue-50">
                <svg class="w-3.5 h-3.5 text-blue-500" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 10l7-7m0 0l7 7M12 3v18"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">
            {{ $totalDisbursements + $totalBulk }}
        </p>
        <p class="text-xs text-gray-400 mt-1">
            {{ number_format($totalDecaisse, 0, ',', ' ') }} XOF
        </p>
    </div>

    {{-- Sous-marchands --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-medium text-gray-500">Marchands</span>
            <div class="w-7 h-7 rounded-lg flex items-center justify-center"
                 style="background-color:#f9eef5;">
                <svg class="w-3.5 h-3.5" style="color:#b13a7e;" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">{{ $totalSubMerchants }}</p>
        <p class="text-xs text-gray-400 mt-1">Marchands actifs</p>
    </div>

</div>

{{-- Graphique --}}
<div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">
        Activité des 7 derniers jours
    </h3>
    <canvas id="activityChart" height="80"></canvas>
</div>

{{-- Transactions récentes --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-gray-700">Transactions récentes</h3>
        <a href="{{ route('portal.transactions.index') }}"
           class="text-xs hover:underline" style="color:#b13a7e;">
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
                <div class="flex items-center justify-between py-3
                            border-b border-gray-50 last:border-0">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center
                                    bg-green-100 text-green-600 text-xs font-bold">
                            ↓
                        </div>
                        <div>
                            <p class="text-xs font-mono text-gray-600">
                                {{ $tx->transaction_id }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $tx->order_reference }} ·
                                {{ $tx->created_at->format('d/m H:i') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">
                                {{ number_format($tx->amount, 0, ',', ' ') }} XOF
                            </p>
                            <p class="text-xs" style="color:#b13a7e;">
                                Net : {{ number_format($tx->net_to_aggregator, 0, ',', ' ') }} XOF
                            </p>
                        </div>
                        @php
                            $colors = [
                                'PENDING'  => 'bg-yellow-100 text-yellow-700',
                                'SUCCESS'  => 'bg-green-100 text-green-700',
                                'FAILED'   => 'bg-red-100 text-red-700',
                                'REFUNDED' => 'bg-gray-100 text-gray-500',
                            ];
                        @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium
                            {{ $colors[$tx->status] ?? 'bg-gray-100 text-gray-500' }}">
                            {{ $tx->status }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Modal demande production --}}
<div id="modal-production"
     class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center
            justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-900">
                🚀 Demande d'accès production
            </h2>
            <button onclick="document.getElementById('modal-production').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <p class="text-sm text-gray-500 mb-4">
            En soumettant cette demande, l'équipe Many sera notifiée.
            Vous recevrez vos credentials production par email sous 24-48h.
        </p>

        <div class="bg-gray-50 rounded-lg p-4 mb-4">
            <p class="text-xs text-gray-400 mb-2">Ce que vous recevrez par email :</p>
            <ul class="text-xs text-gray-600 space-y-1">
                <li>✓ Votre client_id production (agg_live_xxx)</li>
                <li>✓ Votre client_secret production</li>
                <li>✓ Un nouveau mot de passe portail</li>
                <li>✓ La possibilité de modifier votre mot de passe</li>
            </ul>
        </div>

        <textarea id="production-message"
                  placeholder="Message optionnel pour l'équipe Many..."
                  rows="3"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg
                         text-sm mb-4 resize-none"></textarea>

        <div class="flex space-x-3">
            <button type="button"
                    onclick="document.getElementById('modal-production').classList.add('hidden')"
                    class="flex-1 py-2.5 rounded-lg text-sm font-medium
                           border border-gray-300 text-gray-600">
                Annuler
            </button>
            <button type="button"
                    onclick="submitProductionRequest()"
                    class="flex-1 py-2.5 rounded-lg text-sm font-medium text-white"
                    style="background-color:#b13a7e;">
                Envoyer la demande
            </button>
        </div>
    </div>
</div>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Graphique activité
    const ctx = document.getElementById('activityChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($chartData['labels']),
            datasets: [
                {
                    label: 'Collections (XOF)',
                    data: @json($chartData['collections']),
                    borderColor: '#b13a7e',
                    backgroundColor: 'rgba(177, 58, 126, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                },
                {
                    label: 'Décaissements (XOF)',
                    data: @json($chartData['disbursements']),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('fr-FR').format(value) + ' XOF';
                        }
                    }
                }
            }
        },
    });

    // Soumettre demande production
    function submitProductionRequest() {
        const message = document.getElementById('production-message').value;

        fetch("{{ route('portal.dashboard.request.production') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ message }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modal-production').classList.add('hidden');
                alert('✅ ' + data.message);
                window.location.reload();
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(() => alert('Erreur réseau. Réessayez.'));
    }
</script>

@endsection