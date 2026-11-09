{{--
    Bloc "Passage en production" (affiché en mode test).
    Variables : $aggregator, $productionRequest (dernière demande ou null)
--}}
@php $reqStatus = $productionRequest->status ?? null; @endphp

<div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
    <div class="flex items-center gap-3 mb-1">
        <div class="h-9 w-9 rounded-xl bg-[#b13a7e]/10 text-[#b13a7e] flex items-center justify-center">
            @include('portal.partials.icon', ['name' => 'bolt'])
        </div>
        <h3 class="font-semibold">Passer en production</h3>
    </div>

    @if($aggregator->production_enabled || $reqStatus === 'APPROVED')

        <div class="mt-4 rounded-xl bg-emerald-50 ring-1 ring-inset ring-emerald-600/20 p-4">
            <p class="text-sm font-medium text-emerald-800">Accès production approuvé</p>
            <p class="text-xs text-emerald-700 mt-1">
                Vos identifiants de production (client_id, client_secret) vous ont été envoyés par email.
                Utilisez le sélecteur « Production » en haut de la page pour accéder à votre espace réel.
            </p>
        </div>

    @elseif($reqStatus === 'PENDING')

        <div class="mt-4 rounded-xl bg-amber-50 ring-1 ring-inset ring-amber-600/20 p-4">
            <p class="text-sm font-medium text-amber-800">Demande en cours de traitement</p>
            <p class="text-xs text-amber-700 mt-1">
                Envoyée le {{ $productionRequest->created_at->format('d/m/Y à H:i') }}.
                L'équipe Many l'examine et vous répondra par email.
            </p>
        </div>

    @else

        <p class="text-sm text-slate-500 mt-2">
            Vos tests sont terminés ? Envoyez votre demande : l'équipe Many l'examine puis vous transmet
            vos accès de production.
        </p>

        @if($reqStatus === 'REJECTED')
            <div class="mt-4 rounded-xl bg-red-50 ring-1 ring-inset ring-red-600/20 p-4">
                <p class="text-sm font-medium text-red-800">Votre précédente demande a été refusée.</p>
                @if($productionRequest->admin_note)
                    <p class="text-xs text-red-700 mt-1">Motif : {{ $productionRequest->admin_note }}</p>
                @endif
                <p class="text-xs text-red-700 mt-1">Vous pouvez envoyer une nouvelle demande.</p>
            </div>
        @endif

        <textarea id="prod-req-message" rows="3" maxlength="1000"
                  placeholder="Message pour l'équipe Many (optionnel)"
                  class="w-full mt-4 px-4 py-3 rounded-xl border border-slate-300 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-[#b13a7e]/40"></textarea>

        <p id="prod-req-error" class="hidden text-xs text-red-600 mt-2"></p>

        <button type="button" id="prod-req-button" onclick="submitProductionRequest()"
                class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#b13a7e] hover:bg-[#9a3069] transition disabled:opacity-50">
            Demander l'accès production
            @include('portal.partials.icon', ['name' => 'arrow-r', 'class' => 'w-4 h-4'])
        </button>

        <script>
            function submitProductionRequest() {
                if (!confirm("Envoyer la demande de passage en production ?")) return;

                const button = document.getElementById('prod-req-button');
                const error  = document.getElementById('prod-req-error');
                button.disabled = true;
                error.classList.add('hidden');

                fetch("{{ route('portal.dashboard.request.production') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ message: document.getElementById('prod-req-message').value }),
                })
                .then(res => res.json().catch(() => ({ success: false, message: 'Session expirée : rechargez la page.' })))
                .then(data => {
                    if (data.success) { window.location.reload(); return; }
                    error.textContent = data.message || 'Une erreur est survenue.';
                    error.classList.remove('hidden');
                    button.disabled = false;
                })
                .catch(() => {
                    error.textContent = 'Erreur réseau. Réessayez.';
                    error.classList.remove('hidden');
                    button.disabled = false;
                });
            }
        </script>

    @endif
</div>
