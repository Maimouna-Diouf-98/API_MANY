@extends('layouts.portal')

@section('title', 'Transaction — Many')
@section('page_title', 'Détail')

@section('content')

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $date  = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y à H:i:s') : '—';

    $labels = ['collections' => 'Collection', 'disbursements' => 'Décaissement', 'bulks' => 'Paiement de masse'];

    $id = match ($type) { 'collections' => $row->transaction_id, 'disbursements' => $row->disbursement_id, default => $row->bulk_id };

    $amount = $type === 'bulks' ? $row->total_amount : $row->amount;
    $net    = match ($type) {
        'collections'   => $row->net_to_aggregator,
        'disbursements' => $row->net_amount,
        default         => $row->total_amount - $row->many_fee,
    };
    $netLabel = $type === 'collections' ? 'Net crédité sur votre solde' : ($type === 'disbursements' ? 'Montant reçu par le bénéficiaire' : 'Montant net versé');

    $completedAt = $row->completed_at ?? ($env === 'production' && $type === 'collections' ? $row->updated_at ?? null : null);

@endphp

<a href="{{ route('portal.transactions.index', ['type' => $type]) }}" class="text-sm text-slate-500 hover:text-slate-800">← Transactions</a>

<div class="mt-3 mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="text-sm text-slate-500">{{ $labels[$type] }}</p>
        <h2 class="text-2xl font-bold tracking-tight font-mono">{{ $id }}</h2>
    </div>
    <div class="flex items-center gap-3">
        @if($checkoutUrl)
            <a href="{{ $checkoutUrl }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 rounded-xl bg-[#b13a7e] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a3069]">
                Ouvrir la page de paiement
            </a>
        @endif
        @include('portal.partials.badge', ['status' => $row->status])
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6 mb-6">

    {{-- Montants --}}
    <div class="rounded-2xl bg-gradient-to-br from-neutral-900 via-neutral-900 to-[#6b1f48] p-6 text-white">
        <p class="text-sm text-neutral-300">Montant</p>
        <p class="mt-2 text-3xl font-bold tabular">{{ $money($amount) }} <span class="text-base font-medium text-neutral-400">XOF</span></p>

        <div class="mt-6 space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-neutral-400">Frais Many</span><span class="tabular">{{ $money($row->many_fee) }} XOF</span></div>
            <div class="flex justify-between border-t border-white/10 pt-2">
                <span class="text-neutral-300">{{ $netLabel }}</span><span class="font-semibold tabular">{{ $money($net) }} XOF</span>
            </div>
        </div>
    </div>

    {{-- Informations --}}
    <div class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <h3 class="font-semibold mb-4">Informations</h3>
        <dl class="grid sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
            <div><dt class="text-xs text-slate-500">Marchand</dt><dd class="font-medium mt-0.5">{{ $merchant }}</dd></div>
            <div><dt class="text-xs text-slate-500">Environnement</dt><dd class="font-medium mt-0.5">{{ $env === 'production' ? 'Production' : 'Test' }}</dd></div>

            @if($type === 'collections')
                <div><dt class="text-xs text-slate-500">Référence de commande</dt><dd class="font-medium mt-0.5">{{ $row->order_reference ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Client</dt><dd class="font-medium mt-0.5">{{ $row->customer_phone ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Clé d'idempotence</dt><dd class="font-mono text-xs mt-0.5 break-all">{{ $row->idempotency_key ?: '—' }}</dd></div>
            @elseif($type === 'disbursements')
                <div><dt class="text-xs text-slate-500">Référence</dt><dd class="font-medium mt-0.5">{{ $row->reference ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Bénéficiaire</dt><dd class="font-medium mt-0.5">{{ $row->recipient_phone }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Clé d'idempotence</dt><dd class="font-mono text-xs mt-0.5 break-all">{{ $row->idempotency_key ?: '—' }}</dd></div>
            @else
                <div><dt class="text-xs text-slate-500">Libellé</dt><dd class="font-medium mt-0.5">{{ $row->label }}</dd></div>
                <div><dt class="text-xs text-slate-500">Bénéficiaires</dt>
                    <dd class="font-medium mt-0.5">{{ $row->success_count }} réussi(s) · {{ $row->failure_count }} échoué(s) · {{ $row->total_recipients }} au total</dd></div>
            @endif

            <div><dt class="text-xs text-slate-500">Créé le</dt><dd class="font-medium mt-0.5">{{ $date($row->created_at) }}</dd></div>
            <div><dt class="text-xs text-slate-500">Terminé le</dt><dd class="font-medium mt-0.5">{{ $date($completedAt) }}</dd></div>

            @if(!empty($row->failure_reason))
                <div class="sm:col-span-2 rounded-xl bg-red-50 ring-1 ring-inset ring-red-600/20 px-4 py-3">
                    <dt class="text-xs text-red-700">Motif d'échec</dt>
                    <dd class="font-medium text-red-800 mt-0.5">{{ $row->failure_reason }}</dd>
                </div>
            @endif
        </dl>
    </div>
</div>

{{-- Bénéficiaires d'un paiement de masse --}}
@if($type === 'bulks')
    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-semibold">Bénéficiaires ({{ $recipients->count() }})</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-400 border-b border-slate-100 bg-slate-50/60">
                        <th class="px-6 py-3 font-medium">Téléphone</th>
                        <th class="px-6 py-3 font-medium">Référence</th>
                        <th class="px-6 py-3 font-medium text-right">Montant</th>
                        <th class="px-6 py-3 font-medium text-right">Frais</th>
                        <th class="px-6 py-3 font-medium text-right">Net versé</th>
                        <th class="px-6 py-3 font-medium">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recipients as $r)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3.5">{{ $r->phone }}</td>
                            <td class="px-6 py-3.5 text-slate-600">{{ $r->reference ?: '—' }}</td>
                            <td class="px-6 py-3.5 text-right tabular whitespace-nowrap">{{ $money($r->amount) }} XOF</td>
                            <td class="px-6 py-3.5 text-right tabular text-slate-500">{{ $money($r->many_fee) }}</td>
                            <td class="px-6 py-3.5 text-right tabular font-semibold">{{ $money($r->net_amount) }}</td>
                            <td class="px-6 py-3.5">
                                @include('portal.partials.badge', ['status' => $r->status])
                                @if($r->failure_reason)<p class="text-xs text-red-600 mt-1">{{ $r->failure_reason }}</p>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
