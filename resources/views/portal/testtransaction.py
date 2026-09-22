@extends('layouts.portal')

@section('title', 'Transactions — Many Portail')
@section('page_title', 'Transactions')

@section('content')

{{-- Cartes statistiques --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-gray-500">
                Transactions réussies
            </span>
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                 style="background-color:#f9eef5;">
                <svg class="w-4 h-4" style="color:#b13a7e;" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">{{ $totalSuccess }}</p>
        <p class="text-xs text-gray-400 mt-1">Total cumulé</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-gray-500">Volume traité</span>
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
            {{ number_format($totalAmount, 0, ',', ' ') }}
            <span class="text-sm font-normal text-gray-400">XOF</span>
        </p>
        <p class="text-xs text-gray-400 mt-1">Montant brut total</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-gray-500">Net reçu</span>
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                 style="background-color:#f9eef5;">
                <svg class="w-4 h-4" style="color:#b13a7e;" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold" style="color:#b13a7e;">
            {{ number_format($totalNet, 0, ',', ' ') }}
            <span class="text-sm font-normal text-gray-400">XOF</span>
        </p>
        <p class="text-xs text-gray-400 mt-1">Après déduction frais Many</p>
    </div>

</div>

{{-- Filtres --}}
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
    <form method="GET" action="{{ route('portal.transactions.index') }}"
          class="flex items-center space-x-4">
        <select name="status"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                onchange="this.form.submit()">
            <option value="">Tous les statuts</option>
            <option value="PENDING"
                {{ request('status') === 'PENDING' ? 'selected' : '' }}>
                En attente
            </option>
            <option value="SUCCESS"
                {{ request('status') === 'SUCCESS' ? 'selected' : '' }}>
                Réussie
            </option>
            <option value="FAILED"
                {{ request('status') === 'FAILED' ? 'selected' : '' }}>
                Échouée
            </option>
            <option value="REFUNDED"
                {{ request('status') === 'REFUNDED' ? 'selected' : '' }}>
                Remboursée
            </option>
        </select>

        @if(request('status'))
            <a href="{{ route('portal.transactions.index') }}"
               class="text-sm text-gray-400 hover:text-gray-600">
                Réinitialiser
            </a>
        @endif
    </form>
</div>

{{-- Liste --}}
@if($transactions->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <svg class="w-10 h-10 mx-auto mb-3 text-gray-300"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p class="text-sm font-medium text-gray-500">Aucune transaction</p>
        <p class="text-xs text-gray-400 mt-1">
            Les transactions apparaîtront ici après la première collection.
        </p>
    </div>
@else
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                        Transaction
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
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                        Date
                    </th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                        Action
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($transactions as $tx)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4">
                            <p class="font-mono text-xs text-gray-600">
                                {{ $tx->transaction_id }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $tx->customer_phone }}
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-xs text-gray-600">
                                {{ $tx->order_reference }}
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-sm font-semibold text-gray-900">
                                {{ number_format($tx->amount, 0, ',', ' ') }}
                                <span class="text-xs font-normal text-gray-400">XOF</span>
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Frais : {{ number_format($tx->many_fee, 0, ',', ' ') }} XOF
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium" style="color:#b13a7e;">
                                {{ number_format($tx->net_to_aggregator, 0, ',', ' ') }} XOF
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $colors = [
                                    'PENDING'  => 'bg-yellow-100 text-yellow-700',
                                    'SUCCESS'  => 'bg-green-100 text-green-700',
                                    'FAILED'   => 'bg-red-100 text-red-700',
                                    'REFUNDED' => 'bg-gray-100 text-gray-500',
                                ];
                            @endphp
                            <span class="text-xs px-2 py-1 rounded-full font-medium
                                {{ $colors[$tx->status] ?? 'bg-gray-100 text-gray-500' }}">
                                {{ $tx->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-xs text-gray-600">
                                {{ $tx->created_at->format('d/m/Y') }}
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ $tx->created_at->format('H:i') }}
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('portal.transactions.show',
                                             $tx->transaction_id) }}"
                               class="text-xs hover:underline"
                               style="color:#b13a7e;">
                                Détail
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $transactions->links() }}
        </div>
    </div>
@endif

@endsection