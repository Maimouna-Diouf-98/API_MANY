@extends('layouts.portal')

@section('title', 'Applications — Many')
@section('page_title', 'Applications')

@section('content')

@php $isProd = $env === 'production'; @endphp

<div class="flex flex-wrap items-end justify-between gap-4 mb-8">
    <div>
        <h2 class="text-2xl font-bold tracking-tight">Applications</h2>
        <p class="text-sm text-slate-500 mt-1">
            Chaque application dispose de ses identifiants API
            ({{ $isProd ? 'préfixe agg_live_' : 'préfixe agg_sandbox_' }}) et regroupe vos sous-marchands.
        </p>
    </div>
</div>

{{-- Création --}}
<details class="group rounded-2xl bg-white border border-slate-200 shadow-sm mb-8" {{ $errors->any() ? 'open' : '' }}>
    <summary class="flex cursor-pointer list-none items-center justify-between px-6 py-4">
        <span class="inline-flex items-center gap-2 font-semibold">
            <span class="h-8 w-8 rounded-lg bg-[#b13a7e]/10 text-[#b13a7e] flex items-center justify-center">
                @include('portal.partials.icon', ['name' => 'plus', 'class' => 'w-4 h-4'])
            </span>
            Nouvelle application
        </span>
        <span class="text-xs text-slate-400 group-open:hidden">Cliquer pour ouvrir</span>
    </summary>

    <form method="POST" action="{{ route('portal.applications.store') }}" class="px-6 pb-6 pt-2 grid md:grid-cols-2 gap-4">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Nom de l'application</label>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Ex : Boutique en ligne"
                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#b13a7e]/40">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">URL de webhook</label>
            <input type="url" name="webhook_url" value="{{ old('webhook_url') }}" required maxlength="255" placeholder="https://votre-site.sn/webhooks/many"
                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#b13a7e]/40">
        </div>
        <div class="md:col-span-2 flex items-center justify-between gap-4">
            <p class="text-xs text-slate-400">Le client_secret ne sera affiché qu'une seule fois après la création.</p>
            <button class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#b13a7e] hover:bg-[#9a3069]">Créer l'application</button>
        </div>
    </form>
</details>

{{-- Liste --}}
@forelse($applications as $app)
    @if($loop->first)<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">@endif

    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6 flex flex-col">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <span class="h-10 w-10 shrink-0 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                    @include('portal.partials.icon', ['name' => 'cube'])
                </span>
                <div class="min-w-0">
                    <a href="{{ route('portal.applications.show', $app->id) }}" class="font-semibold hover:underline truncate block">{{ $app->name }}</a>
                    <p class="text-xs text-slate-400">Créée le {{ \Carbon\Carbon::parse($app->created_at)->format('d/m/Y') }}</p>
                </div>
            </div>
            @include('portal.partials.badge', ['status' => $app->is_active ? 'ACTIVE' : 'CLOSED'])
        </div>

        <div class="mt-4 rounded-xl bg-slate-50 px-3 py-2 flex items-center justify-between gap-2">
            <code class="text-xs text-slate-600 truncate">{{ $app->client_id }}</code>
            <button type="button" onclick="copyText('{{ $app->client_id }}', this)"
                    class="text-xs font-medium text-[#b13a7e] hover:underline shrink-0">Copier</button>
        </div>

        <div class="mt-4 flex items-center justify-between text-sm">
            <span class="inline-flex items-center gap-2 text-slate-500">
                @include('portal.partials.icon', ['name' => 'store', 'class' => 'w-4 h-4'])
                {{ $app->sub_merchants_count }} sous-marchand(s)
            </span>
            <a href="{{ route('portal.applications.show', $app->id) }}" class="font-semibold text-[#b13a7e] hover:underline">Gérer →</a>
        </div>
    </div>

    @if($loop->last)</div>@endif
@empty
    <div class="rounded-2xl border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
        <span class="mx-auto h-12 w-12 rounded-2xl bg-[#b13a7e]/10 text-[#b13a7e] flex items-center justify-center">
            @include('portal.partials.icon', ['name' => 'cube', 'class' => 'w-6 h-6'])
        </span>
        <p class="mt-4 font-semibold">Aucune application</p>
        <p class="text-sm text-slate-500 mt-1">Créez votre première application pour obtenir vos identifiants API.</p>
    </div>
@endforelse

@endsection
