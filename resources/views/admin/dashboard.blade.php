@extends('layouts.admin')

@section('title', 'Tableau de bord')

@section('content')

@include('admin.partials.tabs', ['active' => 'requests'])

{{-- Credentials affichés une seule fois si l'email a échoué --}}
@if(session('credentials'))
    @php $c = session('credentials'); @endphp
    <div class="mb-6 p-4 rounded-lg bg-yellow-50 border border-yellow-300 text-sm">
        <p class="font-semibold text-yellow-900 mb-2">À transmettre à l'agrégateur (affichés une seule fois)</p>
        <p>Email : <code>{{ $c['email'] }}</code></p>
        <p>Mot de passe : <code>{{ $c['password'] }}</code></p>
        <p>client_id : <code>{{ $c['client_id'] }}</code></p>
        <p>client_secret : <code>{{ $c['client_secret'] }}</code></p>
    </div>
@endif

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    @foreach([
        ['En attente',     $stats['pending'],    'text-yellow-600'],
        ['Approuvées',     $stats['approved'],   'text-green-600'],
        ['Refusées',       $stats['rejected'],   'text-red-600'],
        ['Agrég. sandbox', $stats['sandbox'],    'text-gray-900'],
        ['Agrég. prod',    $stats['production'], 'text-gray-900'],
    ] as [$label, $value, $color])
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500">{{ $label }}</p>
            <p class="text-2xl font-bold mt-1 {{ $color }}">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="grid md:grid-cols-3 gap-6">

    {{-- Demandes --}}
    <div class="md:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700">Demandes de passage en production</h2>
            <div class="flex space-x-2 text-xs">
                @foreach([null => 'Toutes', 'PENDING' => 'En attente', 'APPROVED' => 'Approuvées', 'REJECTED' => 'Refusées'] as $key => $label)
                    <a href="{{ route('admin.dashboard', $key ? ['status' => $key] : []) }}"
                       class="px-2.5 py-1 rounded-full border
                              {{ ($status ?? null) === $key ? 'text-white border-transparent' : 'text-gray-500 border-gray-200' }}"
                       style="{{ ($status ?? null) === $key ? 'background-color:#b13a7e;' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        @forelse($requests as $r)
            @php
                $agg    = $r->aggregator;
                $colors = ['PENDING' => 'bg-yellow-100 text-yellow-700',
                           'APPROVED' => 'bg-green-100 text-green-700',
                           'REJECTED' => 'bg-red-100 text-red-700'];
            @endphp
            <a href="{{ route('admin.requests.show', $r->id) }}"
               class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50 px-2 -mx-2 rounded">
                <div>
                    <p class="text-sm font-medium">{{ $agg->legal_name ?? 'Agrégateur supprimé' }}</p>
                    <p class="text-xs text-gray-400">{{ $agg->email ?? '' }} · {{ $r->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $colors[$r->status] }}">
                    {{ $r->status }}
                </span>
            </a>
        @empty
            <p class="text-sm text-gray-400 text-center py-8">Aucune demande.</p>
        @endforelse

        <div class="mt-4">{{ $requests->links() }}</div>
    </div>

    {{-- Notifications --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Notifications</h2>

        @forelse($notifications as $n)
            @php
                $href = $n->type === 'PRODUCTION_REQUEST' && $n->related_id
                      ? route('admin.requests.show', $n->related_id) : '#';
            @endphp
            <a href="{{ $href }}" class="block py-3 border-b border-gray-100 last:border-0">
                <p class="text-sm {{ $n->read_at ? 'text-gray-500' : 'font-semibold text-gray-900' }}">
                    @unless($n->read_at)<span class="inline-block w-2 h-2 rounded-full bg-red-600 mr-1"></span>@endunless
                    {{ $n->title }}
                </p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $n->body }}</p>
                <p class="text-[11px] text-gray-300 mt-0.5">{{ $n->created_at->diffForHumans() }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-400 text-center py-8">Aucune notification.</p>
        @endforelse
    </div>
</div>
@endsection
