<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Paiement {{ number_format((float) $tx->amount, 0, ',', ' ') }} XOF — Many</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        .tabular { font-variant-numeric: tabular-nums; }
        @keyframes pop { 0% { transform: scale(.6); opacity: 0 } 70% { transform: scale(1.08) } 100% { transform: scale(1); opacity: 1 } }
        .pop { animation: pop .45s ease-out both; }
    </style>
</head>

@php
    $amount = number_format((float) $tx->amount, 0, ',', ' ');
    $isTest = $env !== 'production';
@endphp

<body class="min-h-full bg-gradient-to-b from-neutral-950 via-neutral-900 to-[#4a1633] flex flex-col items-center justify-center px-4 py-8">

    @if($isTest)
        <div class="mb-4 rounded-full bg-amber-400 text-amber-950 text-xs font-semibold px-4 py-1.5">
            Mode test : le paiement est simulé, aucun argent réel n'est débité
        </div>
    @endif

    <main class="w-full max-w-md">

        {{-- En-tête --}}
        <div class="flex items-center justify-center gap-3 mb-6">
            <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-[#d4569b] to-[#8f2b63] flex items-center justify-center text-white text-lg font-bold">M</div>
            <p class="text-white font-semibold text-lg">Many</p>
        </div>

        <div class="rounded-3xl bg-white shadow-2xl overflow-hidden">

            {{-- Montant --}}
            <div class="px-8 pt-8 pb-6 text-center border-b border-slate-100">
                <p class="text-sm text-slate-500">Paiement à</p>
                <p class="mt-0.5 text-lg font-semibold text-slate-900">{{ $merchant }}</p>
                <p class="mt-4 text-4xl font-bold tracking-tight text-slate-900 tabular">
                    {{ $amount }} <span class="text-xl font-semibold text-slate-400">{{ $tx->currency ?? 'XOF' }}</span>
                </p>
                @if($tx->order_reference)
                    <p class="mt-2 text-xs text-slate-400">Commande {{ $tx->order_reference }}</p>
                @endif
            </div>

            <div class="px-8 py-8" aria-live="polite">

                {{-- EN ATTENTE : QR code --}}
                <section data-panel="PENDING" class="{{ $state === 'PENDING' ? '' : 'hidden' }}">
                    @if($qr)
                        <div class="mx-auto w-64 rounded-2xl p-3 ring-2 ring-[#b13a7e]/30 bg-white">
                            <img src="data:image/svg+xml;base64,{{ $qr }}" alt="QR code de paiement Many" class="w-full h-auto">
                        </div>

                        <div class="mt-5 flex items-center justify-center gap-2 text-sm text-slate-600">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#b13a7e] opacity-60"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[#b13a7e]"></span>
                            </span>
                            En attente de votre paiement…
                        </div>
                        <p class="mt-1 text-center text-xs text-slate-400">
                            Expire dans <span id="countdown" class="font-semibold text-slate-600 tabular">--:--</span>
                        </p>

                        <ol class="mt-6 space-y-3 text-sm text-slate-600">
                            <li class="flex gap-3"><span class="h-6 w-6 shrink-0 rounded-full bg-[#b13a7e]/10 text-[#b13a7e] text-xs font-bold flex items-center justify-center">1</span>Ouvrez l'application <strong>Many</strong> sur votre téléphone</li>
                            <li class="flex gap-3"><span class="h-6 w-6 shrink-0 rounded-full bg-[#b13a7e]/10 text-[#b13a7e] text-xs font-bold flex items-center justify-center">2</span>Scannez ce QR code</li>
                            <li class="flex gap-3"><span class="h-6 w-6 shrink-0 rounded-full bg-[#b13a7e]/10 text-[#b13a7e] text-xs font-bold flex items-center justify-center">3</span>Vérifiez le montant et confirmez avec votre code PIN</li>
                        </ol>
                    @else
                        <div class="text-center py-6">
                            <p class="font-semibold text-slate-900">QR code indisponible</p>
                            <p class="text-sm text-slate-500 mt-1">Rechargez la page ou retournez chez le marchand pour relancer le paiement.</p>
                        </div>
                    @endif
                </section>

                {{-- PAYÉ --}}
                <section data-panel="SUCCESS" class="{{ $state === 'SUCCESS' ? '' : 'hidden' }} text-center py-4">
                    <div class="pop mx-auto h-20 w-20 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                        <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <p class="mt-5 text-xl font-bold text-slate-900">Paiement confirmé</p>
                    <p class="mt-1 text-sm text-slate-500">Merci ! {{ $merchant }} a bien reçu votre paiement.</p>
                    <p class="mt-4 text-xs text-slate-400">Référence {{ $tx->transaction_id }} · vous pouvez fermer cette page.</p>
                </section>

                {{-- ÉCHEC --}}
                <section data-panel="FAILED" class="{{ $state === 'FAILED' ? '' : 'hidden' }} text-center py-4">
                    <div class="pop mx-auto h-20 w-20 rounded-full bg-red-100 text-red-600 flex items-center justify-center">
                        <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <p class="mt-5 text-xl font-bold text-slate-900">Paiement non effectué</p>
                    <p id="failure-message" class="mt-1 text-sm text-slate-500">{{ $failure }}</p>
                    <p class="mt-4 text-xs text-slate-400">Retournez chez le marchand pour réessayer.</p>
                </section>

                {{-- EXPIRÉ --}}
                <section data-panel="EXPIRED" class="{{ $state === 'EXPIRED' ? '' : 'hidden' }} text-center py-4">
                    <div class="pop mx-auto h-20 w-20 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                        <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="mt-5 text-xl font-bold text-slate-900">Demande expirée</p>
                    <p class="mt-1 text-sm text-slate-500">Le délai de 10 minutes est dépassé. Retournez chez le marchand pour générer un nouveau QR code.</p>
                </section>

                {{-- REMBOURSÉ --}}
                <section data-panel="REFUNDED" class="{{ $state === 'REFUNDED' ? '' : 'hidden' }} text-center py-4">
                    <div class="pop mx-auto h-20 w-20 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center">
                        <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M3 10h10a5 5 0 010 10H9M3 10l4-4M3 10l4 4"/></svg>
                    </div>
                    <p class="mt-5 text-xl font-bold text-slate-900">Paiement remboursé</p>
                    <p class="mt-1 text-sm text-slate-500">Ce paiement a été remboursé.</p>
                </section>
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-neutral-400 flex items-center justify-center gap-1.5">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Paiement sécurisé par Many
        </p>
    </main>

<script>
    const STATUS_URL = @json($statusUrl);
    const TX_ID      = @json($tx->transaction_id);
    let state        = @json($state);
    let remaining    = {{ (int) $remaining }};
    let timer = null, poller = null;

    const panels = document.querySelectorAll('[data-panel]');

    function show(next, message) {
        state = next;
        panels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== next));
        if (message) document.getElementById('failure-message').textContent = message;

        if (next !== 'PENDING') {
            clearInterval(timer); clearInterval(poller);
            // Permet à la page du marchand (iframe ou popup) de réagir au résultat
            const msg = { source: 'many-checkout', transaction_id: TX_ID, status: next };
            if (window.parent && window.parent !== window) window.parent.postMessage(msg, '*');
            if (window.opener) window.opener.postMessage(msg, '*');
        }
    }

    function renderCountdown() {
        const el = document.getElementById('countdown');
        if (!el) return;
        const m = String(Math.floor(remaining / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        el.textContent = `${m}:${s}`;
    }

    function poll() {
        fetch(STATUS_URL, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(r => r.ok ? r.json() : null)
            .then(d => {
                if (!d) return;
                remaining = d.remaining;
                if (d.status !== state) show(d.status, d.message);
            })
            .catch(() => {});   // réseau instable : on réessaie au prochain cycle
    }

    if (state === 'PENDING') {
        renderCountdown();
        timer  = setInterval(() => {
            remaining = Math.max(0, remaining - 1);
            renderCountdown();
            if (remaining === 0) poll();
        }, 1000);
        poller = setInterval(poll, 3000);
    } else {
        show(state);
    }
</script>
</body>
</html>
