@extends('layouts.portal')

@section('title', $sm->legal_name . ' — Many')
@section('page_title', 'Sous-marchand')

@section('content')

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $typeLabels = ['collections' => 'Collection', 'disbursements' => 'Décaissement', 'bulks' => 'Paiement de masse'];
@endphp

<a href="{{ route('portal.applications.show', $appId) }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $sm->application_name }}</a>

<div class="flex flex-wrap items-center justify-between gap-4 mt-3 mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-2xl font-bold tracking-tight">{{ $sm->legal_name }}</h2>
        @include('portal.partials.badge', ['status' => $sm->status])
    </div>

    <div class="flex flex-wrap gap-2">
        @if(in_array($sm->status, ['PENDING', 'SUSPENDED'], true))
            <form method="POST" action="{{ route('portal.sub-merchants.activate', [$appId, $sm->id]) }}">
                @csrf
                <button class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700">Activer</button>
            </form>
        @endif
        @if($sm->status === 'ACTIVE')
            <form method="POST" action="{{ route('portal.sub-merchants.suspend', [$appId, $sm->id]) }}"
                  onsubmit="return confirm('Suspendre ce sous-marchand ? Il ne pourra plus recevoir d\'opérations.');">
                @csrf
                <button class="rounded-xl px-4 py-2.5 text-sm font-semibold text-orange-700 ring-1 ring-inset ring-orange-300 hover:bg-orange-50">Suspendre</button>
            </form>
        @endif
        @if($sm->status !== 'CLOSED')
            <form method="POST" action="{{ route('portal.sub-merchants.close', [$appId, $sm->id]) }}"
                  onsubmit="return confirm('Clôturer définitivement ce sous-marchand ? Cette action est irréversible.');">
                @csrf
                <button class="rounded-xl px-4 py-2.5 text-sm font-semibold text-red-700 ring-1 ring-inset ring-red-300 hover:bg-red-50">Clôturer</button>
            </form>
        @endif
    </div>
</div>

<div class="grid sm:grid-cols-2 gap-4 mb-6">
    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <p class="text-sm text-slate-500">Total collecté</p>
        <p class="mt-2 text-2xl font-bold tabular">{{ $money($totals['collected']) }} <span class="text-sm font-medium text-slate-400">XOF</span></p>
    </div>
    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <p class="text-sm text-slate-500">Total décaissé</p>
        <p class="mt-2 text-2xl font-bold tabular">{{ $money($totals['disbursed']) }} <span class="text-sm font-medium text-slate-400">XOF</span></p>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <h3 class="font-semibold mb-4">Informations</h3>
        <dl class="text-sm space-y-3">
            <div><dt class="text-slate-500 text-xs">Identifiant</dt><dd class="font-mono text-xs break-all mt-0.5">{{ $sm->id }}</dd></div>
            <div><dt class="text-slate-500 text-xs">Référence</dt><dd class="font-medium mt-0.5">{{ $sm->external_reference }}</dd></div>
            <div><dt class="text-slate-500 text-xs">Activité</dt><dd class="font-medium mt-0.5">{{ $sm->business_type }}</dd></div>
            <div><dt class="text-slate-500 text-xs">Adresse</dt><dd class="mt-0.5">{{ $sm->address }}</dd></div>
            <div><dt class="text-slate-500 text-xs">Webhook</dt><dd class="break-all mt-0.5">{{ $sm->webhook_url }}</dd></div>
            <div><dt class="text-slate-500 text-xs">Créé le</dt><dd class="mt-0.5">{{ \Carbon\Carbon::parse($sm->created_at)->format('d/m/Y') }}</dd></div>
        </dl>
        <button type="button" onclick="copyText('{{ $sm->id }}', this)"
                class="mt-4 text-xs font-semibold text-[#b13a7e] hover:underline">Copier l'identifiant (sub_merchant_id)</button>
    </div>

    <div class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold">Dernières opérations</h3>
            <a href="{{ route('portal.transactions.index', ['sub_merchant_id' => $sm->id]) }}" class="text-sm font-medium text-[#b13a7e] hover:underline">Tout voir</a>
        </div>

        @forelse($operations as $op)
            <a href="{{ route('portal.transactions.show', [$op['id'], 'type' => $op['type']]) }}"
               class="flex items-center justify-between gap-4 px-6 py-3.5 border-b border-slate-50 last:border-0 hover:bg-slate-50">
                <div class="min-w-0">
                    <p class="text-sm font-medium">{{ $typeLabels[$op['type']] }} <span class="font-mono text-xs text-slate-400 ml-1">{{ $op['id'] }}</span></p>
                    <p class="text-xs text-slate-400 truncate">{{ $op['label'] ?: '—' }} · {{ $op['created_at']->format('d/m/Y H:i') }}</p>
                </div>
                <div class="flex items-center gap-4 shrink-0">
                    <p class="text-sm font-semibold tabular">{{ $money($op['amount']) }} XOF</p>
                    @include('portal.partials.badge', ['status' => $op['status']])
                </div>
            </a>
        @empty
            <p class="px-6 py-14 text-center text-sm text-slate-400">Aucune opération pour ce sous-marchand.</p>
        @endforelse
    </div>
</div>

@endsection
