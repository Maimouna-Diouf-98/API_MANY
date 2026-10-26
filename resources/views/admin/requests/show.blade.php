@extends('layouts.admin')

@section('title', 'Demande de production')

@section('content')

<a href="{{ route('admin.dashboard') }}" class="text-xs text-gray-400 hover:text-gray-700">← Retour</a>

@if(session('credentials'))
    @php $c = session('credentials'); @endphp
    <div class="mt-4 p-4 rounded-lg bg-yellow-50 border border-yellow-300 text-sm">
        <p class="font-semibold text-yellow-900 mb-2">À transmettre à l'agrégateur (affichés une seule fois)</p>
        <p>Email : <code>{{ $c['email'] }}</code></p>
        <p>Mot de passe : <code>{{ $c['password'] }}</code></p>
        <p>client_id : <code>{{ $c['client_id'] }}</code></p>
        <p>client_secret : <code>{{ $c['client_secret'] }}</code></p>
    </div>
@endif

<div class="grid md:grid-cols-2 gap-6 mt-4">

    {{-- Identité --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Agrégateur</h2>
        @if($aggregator)
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-gray-400">Raison sociale</dt><dd>{{ $aggregator->legal_name }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Nom commercial</dt><dd>{{ $aggregator->trade_name ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Email</dt><dd>{{ $aggregator->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Téléphone</dt><dd>{{ $aggregator->phone ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Webhook</dt><dd class="truncate max-w-[220px]">{{ $aggregator->webhook_url }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Email vérifié</dt><dd>{{ $aggregator->email_verified_at ? 'Oui' : 'Non' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Inscrit le</dt><dd>{{ $aggregator->created_at->format('d/m/Y') }}</dd></div>
            </dl>
        @else
            <p class="text-sm text-red-600">Agrégateur introuvable.</p>
        @endif

        @if($productionRequest->message)
            <div class="mt-4 p-3 bg-gray-50 rounded-lg text-sm text-gray-600">
                <p class="text-xs text-gray-400 mb-1">Message de l'agrégateur</p>
                {{ $productionRequest->message }}
            </div>
        @endif
    </div>

    {{-- Activité sandbox --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Tests réalisés en sandbox</h2>
        <div class="grid grid-cols-2 gap-3 text-center">
            @foreach([
                'Applications'   => $usage['apps'] ?? 0,
                'Collections OK' => $usage['collections'],
                'Décaissements'  => $usage['disbursements'],
                'Bulks terminés' => $usage['bulks'],
            ] as $label => $value)
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xl font-bold">{{ $value }}</p>
                    <p class="text-xs text-gray-400">{{ $label }}</p>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-400 mt-4">
            Dernière activité :
            {{ $usage['last_activity'] ? \Carbon\Carbon::parse($usage['last_activity'])->format('d/m/Y H:i') : 'aucune' }}
        </p>
    </div>
</div>

{{-- Décision --}}
<div class="bg-white rounded-xl border border-gray-200 p-6 mt-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Décision</h2>

    @if($productionRequest->status === 'PENDING' && !$canDecide)
        <p class="text-sm text-gray-500">
            Vous n'avez pas l'autorisation de valider les demandes de production
            (permission « approve_kyc » requise).
        </p>
    @elseif($productionRequest->status === 'PENDING')

        @if($alreadyInProduction)
            <div class="p-4 rounded-lg bg-blue-50 border border-blue-200">
                <p class="text-sm text-blue-900 mb-3">
                    Cet agrégateur est <strong>déjà actif en production</strong> (activé par commande).
                    Il n'y a rien à créer : vous pouvez simplement clôturer sa demande.
                </p>
                <form method="POST" action="{{ route('admin.requests.markApproved', $productionRequest->id) }}"
                      onsubmit="return confirm('Marquer cette demande comme approuvée ?');">
                    @csrf
                    <button class="px-5 py-2.5 rounded-lg text-sm font-medium text-white"
                            style="background-color:#b13a7e;">
                        Marquer la demande comme approuvée
                    </button>
                </form>
            </div>
        @else
        <div class="grid md:grid-cols-2 gap-6">
            {{-- Approuver --}}
            <form method="POST" action="{{ route('admin.requests.approve', $productionRequest->id) }}"
                  onsubmit="return confirm('Activer cet agrégateur en production ?');">
                @csrf
                <p class="text-sm font-medium text-gray-700 mb-2">Approuver</p>
                <label class="block text-xs text-gray-500 mb-1">Commission (%)</label>
                <input type="number" name="commission" step="0.01" min="0" max="100"
                       value="{{ old('commission', '1.50') }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm mb-2">
                @error('commission')<p class="text-xs text-red-600 mb-2">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-400 mb-3">
                    Un mot de passe est généré automatiquement (identique au mpin) et envoyé par email
                    à l'agrégateur avec son client_id / client_secret production.
                </p>
                <button class="w-full py-2.5 rounded-lg text-sm font-medium text-white"
                        style="background-color:#b13a7e;">
                    Activer en production
                </button>
            </form>

            {{-- Refuser --}}
            <form method="POST" action="{{ route('admin.requests.reject', $productionRequest->id) }}"
                  onsubmit="return confirm('Refuser cette demande ?');">
                @csrf
                <p class="text-sm font-medium text-gray-700 mb-2">Refuser</p>
                <label class="block text-xs text-gray-500 mb-1">Motif (visible par l'agrégateur)</label>
                <textarea name="reason" rows="3" required minlength="5"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm mb-2">{{ old('reason') }}</textarea>
                @error('reason')<p class="text-xs text-red-600 mb-2">{{ $message }}</p>@enderror
                <button class="w-full py-2.5 rounded-lg text-sm font-medium border border-red-300 text-red-700">
                    Refuser la demande
                </button>
            </form>
        </div>
        @endif

    @elseif($productionRequest->status === 'APPROVED')
        <p class="text-sm text-green-700">
            ✅ Approuvée le {{ optional($productionRequest->reviewed_at)->format('d/m/Y à H:i') }}.
        </p>
    @else
        <p class="text-sm text-red-700">
            ❌ Refusée le {{ optional($productionRequest->reviewed_at)->format('d/m/Y à H:i') }}.
        </p>
        <p class="text-sm text-gray-600 mt-2">Motif : {{ $productionRequest->admin_note }}</p>
    @endif
</div>
@endsection
