@extends('layouts.portal')

@section('title', 'Transactions — Many')
@section('page_title', 'Transactions')

@section('content')

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');

    $tabs = ['collections' => 'Collections', 'disbursements' => 'Décaissements', 'bulks' => 'Paiements de masse'];
    $idCol = ['collections' => 'transaction_id', 'disbursements' => 'disbursement_id', 'bulks' => 'bulk_id'][$type];

    $statusOptions = [
        'collections'   => ['PENDING' => 'En attente', 'SUCCESS' => 'Réussi', 'FAILED' => 'Échoué', 'REFUNDED' => 'Remboursé'],
        'disbursements' => ['PENDING' => 'En attente', 'SUCCESS' => 'Réussi', 'FAILED' => 'Échoué'],
        'bulks'         => ['PENDING' => 'En attente', 'PROCESSING' => 'En cours', 'COMPLETED' => 'Terminé', 'FAILED' => 'Échoué'],
    ][$type];

    $hasFilters = collect($filters)->except('type')->filter()->isNotEmpty();
@endphp

<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold tracking-tight">Transactions</h2>
        <p class="text-sm text-slate-500 mt-1">{{ $env === 'production' ? 'Opérations réelles' : 'Opérations de test' }} de votre compte.</p>
    </div>
    <a href="{{ route('portal.transactions.export', array_merge(request()->except('page'), ['type' => $type])) }}"
       class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
        @include('portal.partials.icon', ['name' => 'download', 'class' => 'w-4 h-4']) Exporter en CSV
    </a>
</div>

{{-- Onglets --}}
<div class="flex gap-6 border-b border-slate-200 mb-6 overflow-x-auto">
    @foreach($tabs as $key => $label)
        <a href="{{ route('portal.transactions.index', ['type' => $key]) }}"
           class="pb-3 text-sm font-semibold whitespace-nowrap border-b-2 -mb-px transition
                  {{ $type === $key ? 'border-[#b13a7e] text-[#b13a7e]' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- Filtres --}}
<form method="GET" action="{{ route('portal.transactions.index') }}"
      class="rounded-2xl bg-white border border-slate-200 shadow-sm p-4 mb-6 grid gap-3 md:grid-cols-6">
    <input type="hidden" name="type" value="{{ $type }}">

    <div class="md:col-span-2 relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">@include('portal.partials.icon', ['name' => 'search', 'class' => 'w-4 h-4'])</span>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher (référence, téléphone…)"
               class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#b13a7e]/40">
    </div>

    <select name="status" class="px-3 py-2.5 rounded-xl border border-slate-300 text-sm bg-white">
        <option value="">Tous les statuts</option>
        @foreach($statusOptions as $value => $label)
            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="sub_merchant_id" class="px-3 py-2.5 rounded-xl border border-slate-300 text-sm bg-white">
        <option value="">Tous les marchands</option>
        @foreach($subMerchants as $sm)
            <option value="{{ $sm->id }}" @selected(($filters['sub_merchant_id'] ?? '') === $sm->id)>{{ $sm->legal_name }}</option>
        @endforeach
    </select>

    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="px-3 py-2.5 rounded-xl border border-slate-300 text-sm" title="Du">
    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="px-3 py-2.5 rounded-xl border border-slate-300 text-sm" title="Au">

    <div class="md:col-span-6 flex items-center gap-3">
        <button class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white bg-[#b13a7e] hover:bg-[#9a3069]">Filtrer</button>
        @if($hasFilters)
            <a href="{{ route('portal.transactions.index', ['type' => $type]) }}" class="text-sm text-slate-500 hover:text-slate-800">Réinitialiser</a>
        @endif
        <span class="ml-auto text-xs text-slate-400">{{ $items->total() }} résultat(s)</span>
    </div>
</form>

{{-- Tableau --}}
<div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-slate-400 border-b border-slate-100 bg-slate-50/60">
                    <th class="px-5 py-3 font-medium">Identifiant</th>
                    @if($type === 'collections')
                        <th class="px-5 py-3 font-medium">Commande</th>
                        <th class="px-5 py-3 font-medium">Client</th>
                    @elseif($type === 'disbursements')
                        <th class="px-5 py-3 font-medium">Référence</th>
                        <th class="px-5 py-3 font-medium">Bénéficiaire</th>
                    @else
                        <th class="px-5 py-3 font-medium">Libellé</th>
                        <th class="px-5 py-3 font-medium">Bénéficiaires</th>
                    @endif
                    <th class="px-5 py-3 font-medium">Marchand</th>
                    <th class="px-5 py-3 font-medium text-right">Montant</th>
                    <th class="px-5 py-3 font-medium text-right">Frais</th>
                    <th class="px-5 py-3 font-medium">Statut</th>
                    <th class="px-5 py-3 font-medium">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $row)
                    @php
                        $id     = $row->{$idCol};
                        $amount = $type === 'bulks' ? $row->total_amount : $row->amount;
                    @endphp
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('portal.transactions.show', [$id, 'type' => $type]) }}"
                               class="font-mono text-xs font-medium text-[#b13a7e] hover:underline">{{ $id }}</a>
                        </td>
                        @if($type === 'collections')
                            <td class="px-5 py-3.5">{{ $row->order_reference ?: '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $row->customer_phone ?: '—' }}</td>
                        @elseif($type === 'disbursements')
                            <td class="px-5 py-3.5">{{ $row->reference ?: '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $row->recipient_phone }}</td>
                        @else
                            <td class="px-5 py-3.5">{{ $row->label }}</td>
                            <td class="px-5 py-3.5 text-slate-600 tabular">{{ $row->success_count }}/{{ $row->total_recipients }}</td>
                        @endif
                        <td class="px-5 py-3.5 text-slate-600">{{ $names[$row->sub_merchant_id] ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-right font-semibold tabular whitespace-nowrap">{{ $money($amount) }} XOF</td>
                        <td class="px-5 py-3.5 text-right text-slate-500 tabular whitespace-nowrap">{{ $money($row->many_fee) }}</td>
                        <td class="px-5 py-3.5">@include('portal.partials.badge', ['status' => $row->status])</td>
                        <td class="px-5 py-3.5 text-slate-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-16 text-center text-slate-400">
                            @include('portal.partials.icon', ['name' => 'swap', 'class' => 'w-8 h-8 mx-auto mb-2'])
                            <p class="text-sm">Aucun résultat.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($items->hasPages())
        <div class="px-5 py-4 border-t border-slate-100">{{ $items->links() }}</div>
    @endif
</div>

@endsection
