@extends('layouts.portal')

@section('title', 'Application — Many Portail')
@section('page_title', $application->name )

@section('content')

<div class="max-w-4xl">

    <a href="{{ route('portal.applications.index') }}"
       class="text-sm hover:underline mb-6 inline-block"
       style="color:#b13a7e;">
        ← Retour aux applications
    </a>

    {{-- Alerte secret --}}
    @if($plainSecret)
        <div class="border rounded-xl p-4 mb-6"
             style="background-color:#fff8e6; border-color:#f59e0b;">
            <div class="flex items-start">
                <span class="text-yellow-500 mr-3 mt-0.5 text-lg">⚠</span>
                <div>
                    <p class="text-sm font-semibold text-yellow-800">
                        Notez votre client_secret maintenant
                    </p>
                    <p class="text-xs text-yellow-700 mt-1">
                        Il ne sera plus jamais affiché après avoir quitté cette page.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Infos application --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700">
                Informations de l'application
            </h2>
            <div class="flex items-center space-x-2">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium
                    {{ $application->is_active
                        ? 'bg-green-100 text-green-700'
                        : 'bg-gray-100 text-gray-500' }}">
                    {{ $application->is_active ? 'Active' : 'Inactive' }}
                </span>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                      style="background-color:#f9eef5; color:#b13a7e;">
                    {{ $application->environment }}
                </span>
            </div>
        </div>

        <div class="space-y-4">
            {{-- Client ID --}}
            <div>
                <label class="block text-xs text-gray-400 mb-1">client_id</label>
                <div class="flex items-center space-x-2">
                    <code class="flex-1 bg-gray-50 border border-gray-200 rounded-lg
                                 px-3 py-2 text-xs font-mono text-gray-800">
                        {{ $application->client_id }}
                    </code>
                    <button onclick="navigator.clipboard.writeText('{{ $application->client_id }}')"
                            class="text-xs px-3 py-2 border border-gray-200
                                   rounded-lg text-gray-500 hover:text-gray-700">
                        Copier
                    </button>
                </div>
            </div>

            {{-- Client Secret --}}
            <div>
                <label class="block text-xs text-gray-400 mb-1">client_secret</label>
                @if($plainSecret)
                    <div class="flex items-center space-x-2">
                        <code class="flex-1 bg-yellow-50 border border-yellow-200
                                     rounded-lg px-3 py-2 text-xs font-mono text-gray-800">
                            {{ $plainSecret }}
                        </code>
                        <button onclick="navigator.clipboard.writeText('{{ $plainSecret }}')"
                                class="text-xs px-3 py-2 border border-gray-200
                                       rounded-lg text-gray-500 hover:text-gray-700">
                            Copier
                        </button>
                    </div>
                    <p class="text-xs text-yellow-600 mt-1">
                        ⚠ Visible une seule fois
                    </p>
                @else
                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                        <span class="text-xs font-mono text-gray-400">
                            ••••••••••••••••••••••••••••••••
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        Secret masqué. Supprimez et recréez l'application si vous l'avez perdu.
                    </p>
                @endif
            </div>

            {{-- Webhook --}}
            <div>
                <label class="block text-xs text-gray-400 mb-1">webhook_url</label>
                <div class="flex items-center space-x-2">
                    <code class="flex-1 bg-gray-50 border border-gray-200 rounded-lg
                                 px-3 py-2 text-xs font-mono text-gray-800">
                        {{ $application->webhook_url }}
                    </code>
                    <button onclick="navigator.clipboard.writeText('{{ $application->webhook_url }}')"
                            class="text-xs px-3 py-2 border border-gray-200
                                   rounded-lg text-gray-500 hover:text-gray-700">
                        Copier
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                <p class="text-xs text-gray-400">
                    Créée le {{ $application->created_at->format('d/m/Y à H:i') }}
                </p>
                <form method="POST"
                      action="{{ route('portal.applications.destroy', $application->id) }}"
                      onsubmit="return confirm('Supprimer cette application ?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-red-500 hover:underline">
                        Supprimer l'application
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Sous-marchands --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700">
                Sous-marchands
                <span class="ml-2 text-xs px-2 py-0.5 rounded-full font-medium"
                      style="background-color:#f9eef5; color:#b13a7e;">
                    {{ $subMerchants->count() }}
                </span>
            </h2>
            <button onclick="document.getElementById('modal-sm').classList.remove('hidden')"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium text-white"
                    style="background-color:#b13a7e;">
                + Ajouter un marchand
            </button>
        </div>

        @if($subMerchants->isEmpty())
            <div class="text-center py-8 border-2 border-dashed border-gray-200 rounded-xl">
                <p class="text-sm text-gray-400">Aucun sous-marchand pour cette application.</p>
                <button onclick="document.getElementById('modal-sm').classList.remove('hidden')"
                        class="mt-2 text-sm font-medium hover:underline"
                        style="color:#b13a7e;">
                    Créer le premier →
                </button>
            </div>
        @else
            <div class="space-y-3">
                @foreach($subMerchants as $sm)
                    <a href="{{ route('portal.sub-merchants.show', [$application->id, $sm->id]) }}"
                       class="flex items-center justify-between p-4 rounded-xl border
                              border-gray-100 hover:border-gray-200 hover:bg-gray-50
                              transition block">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $sm->legal_name }}
                            </p>
                            @if($sm->trade_name)
                                <p class="text-xs text-gray-400">{{ $sm->trade_name }}</p>
                            @endif
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $sm->email }} · {{ $sm->phone }}
                            </p>
                        </div>
                        <div class="flex items-center space-x-3">
                            @php
                                $colors = [
                                    'PENDING'   => 'bg-yellow-100 text-yellow-700',
                                    'ACTIVE'    => 'bg-green-100 text-green-700',
                                    'SUSPENDED' => 'bg-orange-100 text-orange-700',
                                    'CLOSED'    => 'bg-gray-100 text-gray-500',
                                ];
                            @endphp
                            <span class="text-xs px-2 py-1 rounded-full font-medium
                                {{ $colors[$sm->status] ?? 'bg-gray-100 text-gray-500' }}">
                                {{ $sm->status }}
                            </span>
                            <svg class="w-4 h-4 text-gray-400"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</div>

{{-- Modal création sous-marchand --}}
<div id="modal-sm"
     class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center
            justify-center z-50 overflow-y-auto py-8">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg mx-4 shadow-xl">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">
                    Nouveau sous-marchand
                </h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    Application : {{ $application->name }}
                </p>
            </div>
            <button onclick="document.getElementById('modal-sm').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

       <form method="POST"
      action="{{ route('portal.sub-merchants.store', $application->id) }}"
      class="space-y-4">
    @csrf

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Raison sociale
        </label>
        <input type="text" name="legal_name"
               placeholder="Maya Store SARL"
               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm"
               required>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Type d'activité
        </label>
        <input type="text" name="business_type"
               placeholder="Ex: Commerce, Restaurant, Transport..."
               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm"
               required>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Adresse
        </label>
        <textarea name="address"
                  placeholder="Dakar, Plateau, Rue 10"
                  rows="2"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm"
                  required></textarea>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            URL de webhook
        </label>
        <input type="url" name="webhook_url"
               placeholder="https://mayastore.sn/webhooks/many"
               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm"
               required>
    </div>

    <div class="flex space-x-3 pt-2">
        <button type="button"
                onclick="document.getElementById('modal-sm').classList.add('hidden')"
                class="flex-1 py-2.5 rounded-lg text-sm font-medium
                       border border-gray-300 text-gray-600">
            Annuler
        </button>
        <button type="submit"
                class="flex-1 py-2.5 rounded-lg text-sm font-medium text-white"
                style="background-color:#b13a7e;">
            Créer
        </button>
    </div>
</form>
    </div>
</div>

@endsection