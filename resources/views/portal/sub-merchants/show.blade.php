@extends('layouts.portal')

@section('title', 'Sous-marchand — Many Portail')

@section('page_title', $subMerchant->legal_name)

@section('content')

<div class="max-w-3xl">

    <a href="{{ route('portal.applications.show', $application->id) }}"
       class="text-sm hover:underline mb-6 inline-block"
       style="color:#b13a7e;">
        ← Retour à {{ $application->name }}
    </a>

    {{-- Header statut + actions --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">
                    {{ $subMerchant->legal_name }}
                </h2>
                @if($subMerchant->trade_name)
                    <p class="text-sm text-gray-400">{{ $subMerchant->trade_name }}</p>
                @endif
                <p class="text-xs font-mono text-gray-300 mt-1">
                    ID : {{ $subMerchant->id }}
                </p>
            </div>
            @php
                $colors = [
                    'PENDING'   => 'bg-yellow-100 text-yellow-700',
                    'ACTIVE'    => 'bg-green-100 text-green-700',
                    'SUSPENDED' => 'bg-orange-100 text-orange-700',
                    'CLOSED'    => 'bg-gray-100 text-gray-500',
                ];
            @endphp
            <span class="text-sm px-3 py-1.5 rounded-full font-medium
                {{ $colors[$subMerchant->status] ?? 'bg-gray-100 text-gray-500' }}">
                {{ $subMerchant->status }}
            </span>
        </div>

        @if($subMerchant->status !== 'CLOSED')
            <div class="flex items-center space-x-3 pt-4 border-t border-gray-100">

                @if(in_array($subMerchant->status, ['PENDING', 'SUSPENDED']))
                    <form method="POST"
                          action="{{ route('portal.sub-merchants.activate',
                                         [$application->id, $subMerchant->id]) }}">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-xs font-medium
                                       bg-green-100 text-green-700 hover:bg-green-200">
                            ✓ Activer
                        </button>
                    </form>
                @endif

                @if($subMerchant->status === 'ACTIVE')
                    <form method="POST"
                          action="{{ route('portal.sub-merchants.suspend',
                                         [$application->id, $subMerchant->id]) }}">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-xs font-medium
                                       bg-orange-100 text-orange-700 hover:bg-orange-200">
                            ⏸ Suspendre
                        </button>
                    </form>
                @endif

                <form method="POST"
                      action="{{ route('portal.sub-merchants.close',
                                     [$application->id, $subMerchant->id]) }}"
                      onsubmit="return confirm('Clôturer ? Action irréversible.')">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-xs font-medium
                                   bg-red-100 text-red-600 hover:bg-red-200">
                        ✕ Clôturer
                    </button>
                </form>
            </div>
        @endif
    </div>

  
        {{-- Infos générales --}}

<div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Informations</h3>
    <div class="grid grid-cols-2 gap-x-8 gap-y-4">
        {{-- Colonne gauche --}}
        <div>
            <p class="text-xs text-gray-400">Application</p>
            <p class="text-sm font-medium text-gray-800">
                {{ $application->name }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-400">Type d'activité</p>
            <p class="text-sm text-gray-800">
                {{ $subMerchant->business_type }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-400">Référence interne</p>
            <p class="text-sm font-mono text-gray-800">
                {{ $subMerchant->external_reference }}
            </p>
        </div>

        {{-- Colonne droite --}}
        <div>
            <p class="text-xs text-gray-400">Raison sociale</p>
            <p class="text-sm font-medium text-gray-800">
                {{ $subMerchant->legal_name }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-400">Adresse</p>
            <p class="text-sm text-gray-800">
                {{ $subMerchant->address }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-400">Créé le</p>
            <p class="text-sm text-gray-800">
                {{ $subMerchant->created_at->format('d/m/Y à H:i') }}
            </p>
        </div>
    </div>

    {{-- Règlement --}}
    {{-- <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="space-y-3">
            <div>
                <p class="text-xs text-gray-400">Mode de paiement</p>
                <span class="text-xs px-2 py-1 rounded-full font-medium"
                      style="background-color:#f9eef5; color:#b13a7e;">
                    MANY
                </span>
            </div>
        </div>
    </div> --}}
</div>


    {{-- Webhook --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Webhook</h3>
        <div class="flex items-center space-x-3">
            <code class="flex-1 bg-gray-50 border border-gray-200 rounded-lg
                         px-3 py-2 text-xs font-mono text-gray-800">
                {{ $subMerchant->webhook_url }}
            </code>
            <button onclick="navigator.clipboard.writeText('{{ $subMerchant->webhook_url }}')"
                    class="text-xs px-3 py-2 border border-gray-200 rounded-lg
                           text-gray-500 hover:text-gray-700">
                Copier
            </button>
        </div>
    </div>

    {{-- Exemple API --}}
    <div class="bg-black rounded-xl p-6">
        <p class="text-xs text-gray-400 mb-3 font-medium">
            Exemple — Initier un paiement pour ce marchand
        </p>
        <pre class="text-xs text-green-400 font-mono leading-relaxed overflow-x-auto">POST https://sandbox-api.many.sn/v1/transactions

{
  "sub_merchant_id": "{{ $subMerchant->id }}",
  "amount": 25000,
  "currency": "XOF",
  "customer_phone": "+221771234567",
  "order_reference": "CMD-2026-001"
}</pre>
    </div>

</div>

@endsection