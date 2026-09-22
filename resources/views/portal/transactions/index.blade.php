@extends('layouts.portal')

@section('title', 'Transactions — Many Portail')
@section('page_title', 'Transactions & Décaissements')

@section('content')

{{-- Cartes statistiques --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-400 mb-1">Collections réussies</p>
        <p class="text-2xl font-bold text-gray-900">{{ $totalCollections }}</p>
        <p class="text-xs text-gray-400 mt-1">
            {{ number_format($totalAmount, 0, ',', ' ') }} XOF
        </p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-400 mb-1">Décaissements unitaires</p>
        <p class="text-2xl font-bold text-gray-900">{{ $totalDisbursements }}</p>
        <p class="text-xs text-gray-400 mt-1">
            {{ number_format($totalDisbursed, 0, ',', ' ') }} XOF
        </p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-400 mb-1">Paiements de masse</p>
        <p class="text-2xl font-bold text-gray-900">{{ $totalBulk }}</p>
        <p class="text-xs text-gray-400 mt-1">
            {{ number_format($totalBulkAmount, 0, ',', ' ') }} XOF
        </p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-400 mb-1">Total décaissé</p>
        <p class="text-xl font-bold text-gray-900">
            {{ number_format($totalDecaisse, 0, ',', ' ') }}
            <span class="text-xs font-normal text-gray-400">XOF</span>
        </p>
        <p class="text-xs text-gray-400 mt-1">Unitaires + masse</p>
    </div>

</div>

{{-- Filtres --}}
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
    <form method="GET" action="{{ route('portal.transactions.index') }}"
          class="flex items-center space-x-4 flex-wrap gap-3">

        <select name="type"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                onchange="this.form.submit()">
            <option value="all"         {{ $type === 'all'         ? 'selected' : '' }}>
                Tout
            </option>
            <option value="collection"  {{ $type === 'collection'  ? 'selected' : '' }}>
                Collections
            </option>
            <option value="disbursement"{{ $type === 'disbursement'? 'selected' : '' }}>
                Décaissements unitaires
            </option>
            <option value="bulk"        {{ $type === 'bulk'        ? 'selected' : '' }}>
                Paiements de masse
            </option>
        </select>

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
                Réussi
            </option>
            <option value="COMPLETED"
                {{ request('status') === 'COMPLETED' ? 'selected' : '' }}>
                Complété
            </option>
            <option value="FAILED"
                {{ request('status') === 'FAILED' ? 'selected' : '' }}>
                Échoué
            </option>
        </select>

        @if(request('status') || $type !== 'all')
            <a href="{{ route('portal.transactions.index') }}"
               class="text-sm text-gray-400 hover:text-gray-600">
                Réinitialiser
            </a>
        @endif
    </form>
</div>

{{-- Tableau --}}
@if(count($items) === 0)
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <p class="text-sm font-medium text-gray-500">Aucune opération</p>
        <p class="text-xs text-gray-400 mt-1">
            Les transactions et décaissements apparaîtront ici.
        </p>
    </div>
@else
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                        Type
                    </th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                        ID
                    </th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                        Détails
                    </th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                        Montant
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
                @foreach($items as $item)
                    <tr class="hover:bg-gray-50 transition">

                        {{-- Type --}}
                        <td class="px-6 py-4">
                            @if($item['type'] === 'COLLECTION')
                                <span class="text-xs px-2 py-1 rounded-full font-medium
                                             bg-green-100 text-green-700">
                                    ↓ Collection
                                </span>
                            @elseif($item['type'] === 'DISBURSEMENT')
                                <span class="text-xs px-2 py-1 rounded-full font-medium
                                             bg-blue-100 text-red-800">
                                    ↑ Décaissement
                                </span>
                            @else
                                <span class="text-xs px-2 py-1 rounded-full font-medium
                                             bg-purple-100 text-orange-700">
                                    ↑↑ Masse
                                </span>
                            @endif
                        </td>

                        {{-- ID --}}
                        <td class="px-6 py-4">
                            <p class="font-mono text-xs text-gray-600">
                                {{ $item['id'] }}
                            </p>
                        </td>

                        {{-- Détails --}}
                        <td class="px-6 py-4">
                            @if($item['type'] === 'BULK_PAYMENT')
                                <p class="text-xs font-medium text-gray-700">
                                    {{ $item['label'] }}
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $item['recipients'] }} bénéficiaires
                                </p>
                            @else
                                <p class="text-xs text-gray-600">
                                    {{ $item['phone'] }}
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $item['reference'] ?? '—' }}
                                </p>
                            @endif
                        </td>

                        {{-- Montant --}}
                        <td class="px-6 py-4">
                            <p class="text-sm font-semibold
                                {{ $item['type'] === 'COLLECTION'
                                    ? 'text-green-600'
                                    : ($item['type'] === 'DISBURSEMENT'
                                        ? 'text-red-800'
                                        : 'text-orange-800') }}">
                                {{ $item['type'] === 'COLLECTION' ? '+' : '-' }}
                                {{ number_format($item['amount'], 0, ',', ' ') }} XOF
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Frais : {{ number_format($item['many_fee'], 0, ',', ' ') }} XOF
                            </p>
                            @if($item['type'] === 'COLLECTION')
                                <p class="text-xs mt-0.5" style="color:#b13a7e;">
                                    Net : {{ number_format($item['net'], 0, ',', ' ') }} XOF
                                </p>
                            @endif
                        </td>

                        {{-- Statut --}}
                        <td class="px-6 py-4">
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
                            <span class="text-xs px-2 py-1 rounded-full font-medium
                                {{ $colors[$item['status']] ?? 'bg-gray-100 text-gray-500' }}">
                                {{ $item['status'] }}
                            </span>
                        </td>

                        {{-- Date --}}
                        <td class="px-6 py-4">
                            <p class="text-xs text-gray-600">
                                {{ \Carbon\Carbon::parse($item['created_at'])->format('d/m/Y') }}
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ \Carbon\Carbon::parse($item['created_at'])->format('H:i') }}
                            </p>
                        </td>

                        {{-- Action --}}
                        <td class="px-6 py-4">
                            <a href="{{ route('portal.transactions.show', $item['id']) }}"
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
        <div class="px-6 py-4 border-t border-gray-100 flex items-center
                    justify-between text-xs text-gray-400">
            <span>{{ $total }} opération(s) au total</span>
            <div class="flex space-x-2">
                @if($page > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}"
                       class="px-3 py-1 border border-gray-200 rounded hover:bg-gray-50">
                        ← Précédent
                    </a>
                @endif
                @if($page * $perPage < $total)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}"
                       class="px-3 py-1 border border-gray-200 rounded hover:bg-gray-50">
                        Suivant →
                    </a>
                @endif
            </div>
        </div>
    </div>
@endif

@endsection