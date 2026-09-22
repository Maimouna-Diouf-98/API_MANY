@extends('layouts.portal')

@section('title', 'Applications — Many Portail')
@section('page_title', 'Applications')

@section('content')

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500">
        Gérez vos clés d'accès à l'API Many.
    </p>
    <button onclick="document.getElementById('modal-create').classList.remove('hidden')"
            class="px-4 py-2 rounded-lg text-sm font-medium text-white"
            style="background-color:#b13a7e;">
        + Nouvelle application
    </button>
</div>

@if($applications->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <svg class="w-10 h-10 mx-auto mb-3 text-gray-300"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
        </svg>
        <p class="text-sm font-medium text-gray-500">Aucune application créée</p>
        <p class="text-xs text-gray-400 mt-1">
            Créez votre première application pour obtenir vos clés API.
        </p>
    </div>
@else
    <div class="space-y-4">
        @foreach($applications as $app)
            <div class="bg-white rounded-xl border border-gray-200 p-6
                        hover:border-gray-300 transition cursor-pointer"
                 onclick="window.location='{{ route('portal.applications.show', $app->id) }}'">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                             style="background-color:#f9eef5;">
                            <svg class="w-5 h-5" style="color:#b13a7e;"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    {{ $app->name }}
                                </h3>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                    {{ $app->is_active
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-gray-100 text-gray-500' }}">
                                    {{ $app->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                      style="background-color:#f9eef5; color:#b13a7e;">
                                    {{ $app->environment }}
                                </span>
                            </div>
                            <p class="text-xs font-mono text-gray-400 mt-0.5">
                                {{ $app->client_id }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-6">
                        <div class="text-center">
                            <p class="text-lg font-bold text-gray-900">
                                {{ $app->sub_merchants_count }}
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ $app->sub_merchants_count > 1
                                    ? 'Sous-marchands'
                                    : 'Sous-marchand' }}
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- Modal création --}}
<div id="modal-create"
     class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center
            justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-900">Nouvelle application</h2>
            <button onclick="document.getElementById('modal-create').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form method="POST" action="{{ route('portal.applications.store') }}"
              class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Nom de l'application
                </label>
                <input type="text" name="name"
                       placeholder="Mon système de paiement"
                       class="w-full px-4 py-2.5 border border-gray-300
                              rounded-lg text-sm"
                       required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    URL de webhook
                </label>
                <input type="url" name="webhook_url"
                       placeholder="https://monsite.sn/webhooks/many"
                       class="w-full px-4 py-2.5 border border-gray-300
                              rounded-lg text-sm"
                       required>
            </div>
            <div class="flex space-x-3 pt-2">
                <button type="button"
                        onclick="document.getElementById('modal-create').classList.add('hidden')"
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