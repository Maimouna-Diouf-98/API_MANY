@extends('layouts.portal')

@section('title', 'Mot de passe — Many')
@section('page_title', 'Mot de passe')

@section('content')

<div class="max-w-xl">
    <h2 class="text-2xl font-bold tracking-tight">Modifier mon mot de passe</h2>
    <p class="text-sm text-slate-500 mt-1">Compte : {{ $aggregator->email }}</p>

    @if($hasProduction)
        <div class="mt-6 rounded-2xl bg-blue-50 ring-1 ring-inset ring-blue-600/20 px-5 py-4 text-sm text-blue-900">
            Ce mot de passe sert aussi de <strong>code PIN</strong> pour confirmer vos décaissements et paiements de masse
            en production. Le changement s'applique aux deux en même temps.
        </div>
    @endif

    <div class="mt-6 rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
        <div id="pwd-success" class="hidden mb-4 rounded-xl bg-emerald-50 ring-1 ring-inset ring-emerald-600/20 px-4 py-3 text-sm text-emerald-800"></div>
        <div id="pwd-error" class="hidden mb-4 rounded-xl bg-red-50 ring-1 ring-inset ring-red-600/20 px-4 py-3 text-sm text-red-800"></div>

        <form id="pwd-form" class="space-y-5" onsubmit="return submitPassword(event)">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1" for="current_password">Mot de passe actuel</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#b13a7e]/40">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1" for="password">Nouveau mot de passe</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#b13a7e]/40">
                <p class="text-xs text-slate-400 mt-1">8 caractères minimum, différent de l'actuel.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1" for="password_confirmation">Confirmer le nouveau mot de passe</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#b13a7e]/40">
            </div>

            <button id="pwd-button" class="w-full py-3 rounded-xl text-sm font-semibold text-white bg-[#b13a7e] hover:bg-[#9a3069] transition disabled:opacity-50">
                Mettre à jour le mot de passe
            </button>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function showMessage(id, text) {
        ['pwd-success', 'pwd-error'].forEach(i => document.getElementById(i).classList.add('hidden'));
        const box = document.getElementById(id);
        box.textContent = text;
        box.classList.remove('hidden');
    }

    function submitPassword(event) {
        event.preventDefault();

        const form = document.getElementById('pwd-form');
        const pwd  = form.password.value;

        if (pwd.length < 8) {
            showMessage('pwd-error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
            return false;
        }
        if (pwd !== form.password_confirmation.value) {
            showMessage('pwd-error', 'La confirmation ne correspond pas au nouveau mot de passe.');
            return false;
        }

        const button     = document.getElementById('pwd-button');
        const controller = new AbortController();
        const timer      = setTimeout(() => controller.abort(), 20000);   // jamais de chargement infini
        button.disabled  = true;

        fetch("{{ route('portal.update.password') }}", {
            method: 'POST',
            signal: controller.signal,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                current_password:      form.current_password.value,
                password:              pwd,
                password_confirmation: form.password_confirmation.value,
            }),
        })
        .then(async res => ({ status: res.status, data: await res.json().catch(() => ({})) }))
        .then(({ status, data }) => {
            if (data.success) {
                form.reset();
                showMessage('pwd-success', data.message || 'Mot de passe mis à jour.');
            } else if (data.errors) {
                showMessage('pwd-error', Object.values(data.errors).flat()[0]);
            } else if (status === 419) {
                showMessage('pwd-error', 'Session expirée : rechargez la page.');
            } else {
                showMessage('pwd-error', data.message || 'Une erreur est survenue.');
            }
        })
        .catch(err => showMessage('pwd-error', err.name === 'AbortError'
            ? 'Le serveur ne répond pas (délai dépassé). Réessayez dans un instant.'
            : 'Erreur réseau. Réessayez.'))
        .finally(() => { clearTimeout(timer); button.disabled = false; });

        return false;
    }
</script>
@endpush
