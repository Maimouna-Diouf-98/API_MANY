@extends('layouts.portal')

@section('title', $application->name . ' — Many')
@section('page_title', 'Application')

@section('content')

@php
    $isProd = $env === 'production';
    $money  = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp

<a href="{{ route('portal.applications.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Applications</a>

<div class="flex flex-wrap items-center justify-between gap-4 mt-3 mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-2xl font-bold tracking-tight">{{ $application->name }}</h2>
        @include('portal.partials.badge', ['status' => $application->is_active ? 'ACTIVE' : 'CLOSED'])
    </div>
</div>

{{-- Secret affiché une seule fois --}}
@if(session('plain_secret'))
    <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5">
        <p class="font-semibold text-amber-900">Votre client_secret : à copier maintenant</p>
        <p class="text-xs text-amber-800 mt-1">Il ne sera plus jamais affiché. En cas de perte, vous devrez en générer un nouveau.</p>
        <div class="mt-3 flex items-center gap-2 rounded-xl bg-white ring-1 ring-amber-300 px-3 py-2">
            <code class="text-sm break-all flex-1">{{ session('plain_secret') }}</code>
            <button type="button" onclick="copyText('{{ session('plain_secret') }}', this)"
                    class="text-xs font-semibold text-[#b13a7e] hover:underline shrink-0">Copier</button>
        </div>
    </div>
@endif

<div class="grid lg:grid-cols-3 gap-6 mb-6">

    {{-- Identifiants --}}
    <div class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <h3 class="font-semibold mb-4">Identifiants API</h3>

        <div class="space-y-4">
            <div>
                <p class="text-xs font-medium text-slate-500 mb-1">client_id</p>
                <div class="rounded-xl bg-slate-50 px-3 py-2.5 flex items-center justify-between gap-2">
                    <code class="text-sm break-all">{{ $application->client_id }}</code>
                    <button type="button" onclick="copyText('{{ $application->client_id }}', this)"
                            class="text-xs font-medium text-[#b13a7e] hover:underline shrink-0">Copier</button>
                </div>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 mb-1">client_secret</p>
                <div class="rounded-xl bg-slate-50 px-3 py-2.5 flex items-center justify-between gap-2">
                    <code class="text-sm text-slate-400">••••••••••••••••••••••••••••••••</code>
                    <form method="POST" action="{{ route('portal.applications.regenerate-secret', $application->id) }}"
                          onsubmit="return confirm('Générer un nouveau secret ? L\'ancien cessera de fonctionner immédiatement et vos tokens en cours seront révoqués.');">
                        @csrf
                        <button class="text-xs font-medium text-[#b13a7e] hover:underline">Régénérer</button>
                    </form>
                </div>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 mb-1">URL de webhook</p>
                <p class="text-sm break-all">{{ $application->webhook_url }}</p>
            </div>
        </div>
    </div>

    {{-- Informations + zone sensible --}}
    <div class="space-y-6">
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
            <h3 class="font-semibold mb-4">Informations</h3>
            <dl class="text-sm space-y-3">
                <div class="flex justify-between"><dt class="text-slate-500">Environnement</dt><dd class="font-medium">{{ $isProd ? 'Production' : 'Test' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Créée le</dt><dd class="font-medium">{{ \Carbon\Carbon::parse($application->created_at)->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Dernière utilisation</dt>
                    <dd class="font-medium">{{ $application->last_used_at ? \Carbon\Carbon::parse($application->last_used_at)->format('d/m/Y H:i') : 'Jamais' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Sous-marchands</dt><dd class="font-medium">{{ $subMerchants->count() }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl bg-white border border-red-200 p-6">
            <h3 class="font-semibold text-red-700 mb-2">Zone sensible</h3>

            @if($isProd && !$application->is_active)
                <p class="text-xs text-slate-500 mb-3">Application désactivée : elle ne peut plus s'authentifier.</p>
                <form method="POST" action="{{ route('portal.applications.reactivate', $application->id) }}">
                    @csrf
                    <button class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold text-white bg-[#b13a7e] hover:bg-[#9a3069]">Réactiver l'application</button>
                </form>
            @else
                <p class="text-xs text-slate-500 mb-3">
                    @if($isProd)
                        Désactiver coupe l'accès API. L'historique de vos opérations est conservé.
                    @else
                        La suppression retire l'application et ses sous-marchands de test.
                    @endif
                </p>
                <form method="POST" action="{{ route('portal.applications.destroy', $application->id) }}"
                      onsubmit="return confirm('{{ $isProd ? 'Désactiver cette application ?' : 'Supprimer cette application et ses sous-marchands ?' }}');">
                    @csrf
                    @method('DELETE')
                    <button class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold text-red-700 ring-1 ring-inset ring-red-300 hover:bg-red-50">
                        {{ $isProd ? "Désactiver l'application" : "Supprimer l'application" }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

{{-- Sous-marchands --}}
<div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-semibold">Sous-marchands ({{ $subMerchants->count() }})</h3>
    </div>

    <details class="group border-b border-slate-100" {{ $errors->any() ? 'open' : '' }}>
        <summary class="flex cursor-pointer list-none items-center gap-2 px-6 py-3 text-sm font-semibold text-[#b13a7e] hover:bg-slate-50">
            @include('portal.partials.icon', ['name' => 'plus', 'class' => 'w-4 h-4']) Nouveau sous-marchand
        </summary>

        <form method="POST" action="{{ route('portal.sub-merchants.store', $application->id) }}" class="px-6 pb-6 pt-2 grid md:grid-cols-2 gap-4">
            @csrf
            @foreach([
                ['legal_name',    'Raison sociale',   'text', 'Maya Store SARL'],
                ['business_type', "Type d'activité",  'text', 'Commerce'],
                ['address',       'Adresse',          'text', 'Dakar, Plateau, Rue 10'],
                ['webhook_url',   'URL de webhook',   'url',  'https://marchand.sn/webhooks/many'],
            ] as [$name, $label, $type, $placeholder])
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ $label }}</label>
                    <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name) }}" required maxlength="500" placeholder="{{ $placeholder }}"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#b13a7e]/40">
                </div>
            @endforeach
            <div class="md:col-span-2 flex items-center justify-between gap-4">
                <p class="text-xs text-slate-400">Le sous-marchand est créé « En attente » : activez-le pour qu'il puisse recevoir des opérations.</p>
                <button class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#b13a7e] hover:bg-[#9a3069]" @disabled(!$application->is_active)>Créer</button>
            </div>
        </form>
    </details>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">Sous-marchand</th>
                    <th class="px-6 py-3 font-medium">Statut</th>
                    <th class="px-6 py-3 font-medium text-right">Collecté</th>
                    <th class="px-6 py-3 font-medium text-right">Décaissé</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($subMerchants as $sm)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3.5">
                            <a href="{{ route('portal.sub-merchants.show', [$application->id, $sm['id']]) }}" class="font-medium hover:underline">{{ $sm['legal_name'] }}</a>
                            <p class="text-xs text-slate-400">{{ $sm['business_type'] }} · {{ $sm['external_reference'] }}</p>
                        </td>
                        <td class="px-6 py-3.5">@include('portal.partials.badge', ['status' => $sm['status']])</td>
                        <td class="px-6 py-3.5 text-right tabular whitespace-nowrap">{{ $money($sm['collected']) }} XOF</td>
                        <td class="px-6 py-3.5 text-right tabular whitespace-nowrap">{{ $money($sm['disbursed']) }} XOF</td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="{{ route('portal.sub-merchants.show', [$application->id, $sm['id']]) }}" class="text-xs font-semibold text-[#b13a7e] hover:underline">Détails →</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400">Aucun sous-marchand pour cette application.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
