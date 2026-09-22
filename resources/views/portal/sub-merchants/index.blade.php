@extends('layouts.portal')

@section('title', 'Sous-marchands — Many Portail')
@section('page_title', 'Sous-marchands')

@section('content')

{{-- Alerte si pas d'application --}}
@if($applications->isEmpty())
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
        <p class="text-sm text-yellow-800">
            Vous devez d'abord créer une application avant d'ajouter
            des sous-marchands.
            <a href="{{ route('portal.applications.index') }}"
               class="font-medium underline">
                Créer une application →
            </a>
        </p>
    </div>
@endif

{{-- Une section par application --}}
@foreach($applications as $application)
    <div class="mb-8">

        {{-- Header application --}}
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                     style="background-color:#f9eef5;">
                    <svg class="w-4 h-4" style="color:#b13a7e;"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">
                        {{ $application->name }}
                    </h2>
                    <p class="text-xs font-mono text-gray-400">
                        {{ $application->client_id }}
                    </p>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                      style="background-color:#f9eef5; color:#b13a7e;">
                    {{ $application->subMerchants->count() }}
                    {{ $application->subMerchants->count() > 1 ? 'marchands' : 'marchand' }}
                </span>
            </div>

            {{-- Bouton "Ajouter un marchand" — modernisé --}}
            <button onclick="openModal('{{ $application->id }}')"
                    class="group inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs
                           font-semibold text-white shadow-sm shadow-pink-900/10
                           transition-all duration-200 hover:shadow-md hover:-translate-y-0.5
                           active:translate-y-0 active:shadow-sm"
                    style="background: linear-gradient(135deg, #b13a7e 0%, #92306a 100%);">
                <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover:rotate-90"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                Ajouter un marchand
            </button>
        </div>

        {{-- Liste des sous-marchands de cette application --}}
        @if($application->subMerchants->isEmpty())
            <div class="bg-white rounded-xl border border-dashed border-gray-200
                        p-8 text-center">
                <p class="text-sm text-gray-400">
                    Aucun sous-marchand pour cette application.
                </p>

                {{-- Bouton "Créer le premier marchand" — modernisé --}}
                <button onclick="openModal('{{ $application->id }}')"
                        class="mt-3 inline-flex items-center gap-1 text-sm font-medium
                               transition-colors duration-150 hover:gap-2"
                        style="color:#b13a7e;">
                    Créer le premier marchand
                    <svg class="w-4 h-4 transition-transform duration-150" fill="none"
                         stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </button>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                                Marchand
                            </th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                                Contact
                            </th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                                Règlement
                            </th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                                Statut
                            </th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-400">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($application->subMerchants as $sm)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <a href="{{ route('portal.sub-merchants.show', $sm->id) }}"
                                       class="font-medium hover:underline"
                                       style="color:#b13a7e;">
                                        {{ $sm->legal_name }}
                                    </a>
                                    @if($sm->trade_name)
                                        <p class="text-xs text-gray-400">
                                            {{ $sm->trade_name }}
                                        </p>
                                    @endif
                                    @if($sm->external_reference)
                                        <p class="text-xs font-mono text-gray-300 mt-0.5">
                                            {{ $sm->external_reference }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-gray-700">{{ $sm->email }}</p>
                                    <p class="text-xs text-gray-400">{{ $sm->phone }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-xs font-medium text-gray-600">
                                        {{ strtoupper($sm->settlement_operator) }}
                                    </p>
                                    <p class="text-xs font-mono text-gray-400">
                                        {{ $sm->settlement_number }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
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
                                </td>
                                <td class="px-6 py-4">
                                    {{-- Boutons d'action — modernisés --}}
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('portal.sub-merchants.show', $sm->id) }}"
                                           class="px-2.5 py-1 rounded-md text-xs font-medium
                                                  transition-colors duration-150 hover:bg-pink-50"
                                           style="color:#b13a7e;">
                                            Détail
                                        </a>

                                        @if(in_array($sm->status, ['PENDING', 'SUSPENDED']))
                                            <form method="POST"
                                                  action="{{ route('portal.sub-merchants.activate', $sm->id) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="px-2.5 py-1 rounded-md text-xs font-medium
                                                               text-green-600 transition-colors duration-150
                                                               hover:bg-green-50">
                                                    Activer
                                                </button>
                                            </form>
                                        @endif

                                        @if($sm->status === 'ACTIVE')
                                            <form method="POST"
                                                  action="{{ route('portal.sub-merchants.suspend', $sm->id) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="px-2.5 py-1 rounded-md text-xs font-medium
                                                               text-orange-500 transition-colors duration-150
                                                               hover:bg-orange-50">
                                                    Suspendre
                                                </button>
                                            </form>
                                        @endif

                                        @if($sm->status !== 'CLOSED')
                                            <form method="POST"
                                                  action="{{ route('portal.sub-merchants.close', $sm->id) }}"
                                                  onsubmit="return confirm('Clôturer ce sous-marchand ? Cette action est irréversible.')">
                                                @csrf
                                                <button type="submit"
                                                        class="px-2.5 py-1 rounded-md text-xs font-medium
                                                               text-red-500 transition-colors duration-150
                                                               hover:bg-red-50">
                                                    Clôturer
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
@endforeach

{{-- Modals de création (un par application) --}}
@foreach($applications as $application)
    <div id="modal-{{ $application->id }}"
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
                <button onclick="closeModal('{{ $application->id }}')"
                        class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                         viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST"
                  action="{{ route('portal.sub-merchants.store') }}"
                  class="space-y-4">
                @csrf
                <input type="hidden" name="application_id"
                       value="{{ $application->id }}">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Raison sociale
                        </label>
                        <input type="text" name="legal_name"
                               placeholder="Maya Store SARL"
                               class="w-full px-4 py-2.5 border border-gray-300
                                      rounded-lg text-sm"
                               required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nom commercial
                            <span class="text-gray-400 text-xs">(optionnel)</span>
                        </label>
                        <input type="text" name="trade_name"
                               placeholder="Maya Store"
                               class="w-full px-4 py-2.5 border border-gray-300
                                      rounded-lg text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Numéro d'enregistrement
                        <span class="text-gray-400 text-xs">(optionnel)</span>
                    </label>
                    <input type="text" name="registration_number"
                           placeholder="SN-DKR-2023-A-5566"
                           class="w-full px-4 py-2.5 border border-gray-300
                                  rounded-lg text-sm">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Email
                        </label>
                        <input type="email" name="email"
                               placeholder="contact@mayastore.sn"
                               class="w-full px-4 py-2.5 border border-gray-300
                                      rounded-lg text-sm"
                               required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Téléphone
                        </label>
                        <input type="text" name="phone"
                               placeholder="+221771234567"
                               class="w-full px-4 py-2.5 border border-gray-300
                                      rounded-lg text-sm"
                               required>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Compte de règlement
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <select name="settlement_type"
                                class="w-full px-3 py-2.5 border border-gray-300
                                       rounded-lg text-sm"
                                required>
                            <option value="">Type</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                        <select name="settlement_operator"
                                class="w-full px-3 py-2.5 border border-gray-300
                                       rounded-lg text-sm"
                                required>
                            <option value="">Opérateur</option>
                            <option value="many">Many</option>
                        </select>
                        <input type="text" name="settlement_number"
                               placeholder="+221781111111"
                               class="w-full px-3 py-2.5 border border-gray-300
                                      rounded-lg text-sm"
                               required>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        URL de webhook
                    </label>
                    <input type="url" name="webhook_url"
                           placeholder="https://mayastore.sn/webhooks/many"
                           class="w-full px-4 py-2.5 border border-gray-300
                                  rounded-lg text-sm"
                           required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Référence interne
                        <span class="text-gray-400 text-xs">(optionnel)</span>
                    </label>
                    <input type="text" name="external_reference"
                           placeholder="MAYA-001"
                           class="w-full px-4 py-2.5 border border-gray-300
                                  rounded-lg text-sm">
                </div>

                {{-- Boutons de la modal — modernisés --}}
                <div class="flex space-x-3 pt-2">
                    <button type="button"
                            onclick="closeModal('{{ $application->id }}')"
                            class="flex-1 py-2.5 rounded-lg text-sm font-medium
                                   border border-gray-300 text-gray-600
                                   transition-colors duration-150 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit"
                            class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white
                                   shadow-sm shadow-pink-900/10 transition-all duration-200
                                   hover:shadow-md hover:-translate-y-0.5 active:translate-y-0"
                            style="background: linear-gradient(135deg, #b13a7e 0%, #92306a 100%);">
                        Créer
                    </button>
                </div>
            </form>
        </div>
    </div>
@endforeach

<script>
    function openModal(appId) {
        document.getElementById('modal-' + appId).classList.remove('hidden');
    }
    function closeModal(appId) {
        document.getElementById('modal-' + appId).classList.add('hidden');
    }
</script>

@endsection