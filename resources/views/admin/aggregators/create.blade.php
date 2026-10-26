@extends('layouts.admin')

@section('title', 'Créer un agrégateur')

@section('content')

@include('admin.partials.tabs', ['active' => 'sandbox'])

<a href="{{ route('admin.aggregators.index', 'sandbox') }}" class="text-xs text-gray-400 hover:text-gray-700">
    ← Agrégateurs sandbox
</a>

<div class="max-w-xl mt-4">
    <h1 class="text-lg font-bold mb-1">Créer un agrégateur (phase test)</h1>
    <p class="text-sm text-gray-400 mb-6">
        Le compte est créé en sandbox avec 1 000 000 XOF fictifs. Un mot de passe est généré
        et envoyé par email avec un lien de vérification (valable 72 h). L'agrégateur doit
        d'abord confirmer son email avant de pouvoir se connecter, puis il pourra demander
        la production depuis son tableau de bord.
    </p>

    @unless($canCreate)
        <p class="mb-6 text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
            Vous n'avez pas l'autorisation de créer un agrégateur.
        </p>
    @endunless

    <form method="POST" action="{{ route('admin.aggregators.store') }}"
          class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        @csrf

        @php
            $fields = [
                ['legal_name',  'Raison sociale',  'text',  true,  'BBS SARL'],
                ['trade_name',  'Nom commercial',  'text',  false, 'BBS Trade'],
                ['email',       'Email',           'email', true,  'contact@entreprise.sn'],
                ['phone',       'Téléphone',       'text',  true,  '+221 77 123 45 67'],
                ['webhook_url', 'URL de webhook',  'url',   true,  'https://entreprise.sn/webhooks/many'],
            ];
        @endphp

        @foreach($fields as [$name, $label, $type, $required, $placeholder])
            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
                </label>
                <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name) }}"
                       placeholder="{{ $placeholder }}" @if($required) required @endif
                       class="w-full px-3 py-2 border rounded-lg text-sm
                              {{ $errors->has($name) ? 'border-red-400' : 'border-gray-300' }}">
                @error($name)
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <p class="text-xs text-gray-400">
            Le téléphone sert aussi à créer son compte production plus tard : il doit
            rester unique parmi les comptes Many.
        </p>

        <button @disabled(!$canCreate)
                class="w-full py-2.5 rounded-lg text-sm font-medium text-white disabled:opacity-40"
                style="background-color:#b13a7e;">
            Créer et envoyer les accès
        </button>
    </form>
</div>
@endsection
