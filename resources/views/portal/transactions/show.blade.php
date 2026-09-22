@extends('layouts.portal')

@section('title', 'Détail — Many Portail')
@section('page_title',
    $type === 'COLLECTION' ? 'Détail collection' :
    ($type === 'DISBURSEMENT' ? 'Détail décaissement' : 'Détail paiement de masse'))

@section('content')

<div class="max-w-3xl">

    <a href="{{ route('portal.transactions.index') }}"
       class="text-sm hover:underline mb-6 inline-block"
       style="color:#b13a7e;">
        ← Retour aux transactions
    </a>

    {{-- Badge type --}}
    <div class="mb-4">
        @if($type === 'COLLECTION')
            <span class="text-xs px-3 py-1 rounded-full font-medium
                         bg-green-100 text-green-700">
                ↓ Collection (encaissement)
            </span>
        @elseif($type === 'DISBURSEMENT')
            <span class="text-xs px-3 py-1 rounded-full font-medium
                         bg-blue-100 text-blue-700">
                ↑ Décaissement unitaire
            </span>
        @else
            <span class="text-xs px-3 py-1 rounded-full font-medium
                         bg-purple-100 text-purple-700">
                ↑↑ Paiement de masse
            </span>
        @endif
    </div>

    {{-- Header statut --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400">
                    @if($type === 'COLLECTION') Transaction ID
                    @elseif($type === 'DISBURSEMENT') Disbursement ID
                    @else Bulk ID
                    @endif
                </p>
                <p class="font-mono text-sm font-medium text-gray-800 mt-0.5">
                    @if($type === 'COLLECTION')      {{ $item->transaction_id }}
                    @elseif($type === 'DISBURSEMENT'){{ $item->disbursement_id }}
                    @else                            {{ $item->bulk_id }}
                    @endif
                </p>
                @if($type === 'BULK_PAYMENT')
                    <p class="text-sm font-semibold text-gray-700 mt-1">
                        {{ $item->label }}
                    </p>
                @endif
            </div>
            @php
                $colors = [
                    'PENDING'    => 'bg-yellow-100 text-yellow-700',
                    'PROCESSING' => 'bg-blue-100 text-blue-700',
                    'SUCCESS'    => 'bg-green-100 text-green-700',
                    'COMPLETED'  => 'bg-green-100 text-green-700',
                    'FAILED'     => 'bg-red-100 text-red-700',
                ];
            @endphp
            <span class="text-sm px-3 py-1.5 rounded-full font-medium
                {{ $colors[$item->status] ?? 'bg-gray-100 text-gray-500' }}">
                {{ $item->status }}
            </span>
        </div>

        @if(isset($item->failure_reason) && $item->failure_reason)
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mt-4">
                <p class="text-xs text-red-700">
                    Raison : {{ $item->failure_reason }}
                </p>
            </div>
        @endif
    </div>

    {{-- Détails financiers --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">
            Détails financiers
        </h3>
        <div class="space-y-3">

            @if($type === 'BULK_PAYMENT')
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Nombre de bénéficiaires</span>
                    <span class="text-sm font-semibold text-gray-900">
                        {{ $item->total_recipients }}
                        ({{ $item->success_count }} réussis,
                         {{ $item->failure_count }} échoués)
                    </span>
                </div>
            @endif

            <div class="flex justify-between">
                <span class="text-sm text-gray-500">Montant total</span>
                <span class="text-sm font-semibold text-gray-900">
                    {{ number_format($item->amount ?? $item->total_amount, 0, ',', ' ') }} XOF
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-500">Frais Many (1%)</span>
                <span class="text-sm text-gray-600">
                    - {{ number_format($item->many_fee, 0, ',', ' ') }} XOF
                </span>
            </div>
            <div class="flex justify-between pt-3 border-t border-gray-100">
                <span class="text-sm font-semibold text-gray-700">
                    @if($type === 'COLLECTION')
                        Net reçu sur votre balance
                    @else
                        Net décaissé
                    @endif
                </span>
                <span class="text-sm font-bold" style="color:#b13a7e;">
                    @if($type === 'COLLECTION')
                        {{ number_format($item->net_to_aggregator, 0, ',', ' ') }} XOF
                    @elseif($type === 'DISBURSEMENT')
                        {{ number_format($item->net_amount, 0, ',', ' ') }} XOF
                    @else
                        {{ number_format($item->total_amount - $item->many_fee, 0, ',', ' ') }} XOF
                    @endif
                </span>
            </div>
        </div>
    </div>

    {{-- Informations --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Informations</h3>
        <div class="space-y-3">

            @if($type === 'COLLECTION')
                <div class="flex justify-between">
                    <span class="text-xs text-gray-400">Téléphone client</span>
                    <span class="text-xs text-gray-800">{{ $item->customer_phone }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs text-gray-400">Référence commande</span>
                    <span class="text-xs font-mono text-gray-800">
                        {{ $item->order_reference }}
                    </span>
                </div>
            @elseif($type === 'DISBURSEMENT')
                <div class="flex justify-between">
                    <span class="text-xs text-gray-400">Bénéficiaire</span>
                    <span class="text-xs text-gray-800">{{ $item->recipient_phone }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs text-gray-400">Référence</span>
                    <span class="text-xs font-mono text-gray-800">
                        {{ $item->reference ?? '—' }}
                    </span>
                </div>
            @endif

            @if(isset($item->idempotency_key) && $item->idempotency_key)
                <div class="flex justify-between">
                    <span class="text-xs text-gray-400">Clé idempotence</span>
                    <span class="text-xs font-mono text-gray-800">
                        {{ $item->idempotency_key }}
                    </span>
                </div>
            @endif

            <div class="flex justify-between">
                <span class="text-xs text-gray-400">Devise</span>
                <span class="text-xs text-gray-800">
                    {{ $item->currency }}
                </span>
            </div>

            <div class="flex justify-between">
                <span class="text-xs text-gray-400">Environnement</span>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                      style="background-color:#f9eef5; color:#b13a7e;">
                    {{ $item->environment }}
                </span>
            </div>

            <div class="flex justify-between">
                <span class="text-xs text-gray-400">Créé le</span>
                <span class="text-xs text-gray-800">
                    {{ $item->created_at->format('d/m/Y à H:i:s') }}
                </span>
            </div>

            @if($item->completed_at)
                <div class="flex justify-between">
                    <span class="text-xs text-gray-400">Complété le</span>
                    <span class="text-xs text-gray-800">
                        {{ \Carbon\Carbon::parse($item->completed_at)
                                         ->format('d/m/Y à H:i:s') }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- Liste recipients pour bulk payment --}}
    @if($type === 'BULK_PAYMENT')
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">
                    Bénéficiaires
                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full font-medium"
                          style="background-color:#f9eef5; color:#b13a7e;">
                        {{ $item->total_recipients }}
                    </span>
                </h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                            Téléphone
                        </th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                            Référence
                        </th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                            Montant
                        </th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                            Net reçu
                        </th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                            Statut
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($item->recipients as $recipient)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-xs text-gray-700">
                                {{ $recipient->phone }}
                            </td>
                            <td class="px-6 py-3 text-xs font-mono text-gray-500">
                                {{ $recipient->reference ?? '—' }}
                            </td>
                            <td class="px-6 py-3 text-xs font-semibold text-gray-900">
                                {{ number_format($recipient->amount, 0, ',', ' ') }} XOF
                            </td>
                            <td class="px-6 py-3 text-xs" style="color:#b13a7e;">
                                {{ number_format($recipient->net_amount, 0, ',', ' ') }} XOF
                            </td>
                            <td class="px-6 py-3">
                                @php
                                    $colors = [
                                        'PENDING' => 'bg-yellow-100 text-yellow-700',
                                        'SUCCESS' => 'bg-green-100 text-green-700',
                                        'FAILED'  => 'bg-red-100 text-red-700',
                                    ];
                                @endphp
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                    {{ $colors[$recipient->status] ?? '' }}">
                                    {{ $recipient->status }}
                                </span>
                                @if($recipient->failure_reason)
                                    <p class="text-xs text-red-500 mt-0.5">
                                        {{ $recipient->failure_reason }}
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>

@endsection