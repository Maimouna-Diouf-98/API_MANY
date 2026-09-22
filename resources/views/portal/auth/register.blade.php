<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Many — Créer un compte</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .btn-primary { background-color: #b13a7e; color: white; transition: background-color 0.2s; }
        .btn-primary:hover { background-color: #8e2e64; }
        .input-focus:focus { outline: none; border-color: #b13a7e; box-shadow: 0 0 0 3px rgba(177,58,126,0.15); }
        .text-primary { color: #b13a7e; }
    </style>
</head>
<body class="bg-gray-50 font-sans">

<div class="min-h-screen flex">

    {{-- Panneau gauche --}}
    <div class="hidden lg:flex lg:w-1/2 bg-black flex-col justify-between p-12">
        <div>
            <span class="text-2xl font-bold text-white">Many</span>
        </div>
        <div>
            <h2 class="text-4xl font-bold text-white leading-tight">
                Rejoignez<br>
                <span style="color:#b13a7e;">l'écosystème</span><br>
                Many
            </h2>
            <p class="mt-4 text-gray-400 text-sm leading-relaxed">
                Créez votre compte agrégateur et commencez
                à intégrer les services de paiement Many
                dès aujourd'hui en environnement sandbox.
            </p>
            <div class="mt-6 space-y-3">
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2" style="color:#b13a7e;" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Accès sandbox immédiat
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2" style="color:#b13a7e;" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Clés API générées automatiquement
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2" style="color:#b13a7e;" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    1 000 000 XOF virtuel pour vos tests
                </div>
            </div>
        </div>
        <div class="flex space-x-2">
            <div class="w-2 h-2 rounded-full" style="background-color:#b13a7e;"></div>
            <div class="w-2 h-2 rounded-full bg-gray-600"></div>
            <div class="w-2 h-2 rounded-full bg-gray-600"></div>
        </div>
    </div>

    {{-- Panneau droit --}}
    <div class="w-full lg:w-1/2 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">

            <div class="lg:hidden mb-8 text-center">
                <span class="text-2xl font-bold text-primary">Many</span>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-1">Créer un compte</h1>
            <p class="text-sm text-gray-500 mb-8">Remplissez les informations de votre entreprise</p>

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
                    <ul class="space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div id="successMessage" style="display:none; background:#f0fdf4;
     border:1px solid #bbf7d0; border-radius:12px; padding:20px;
     text-align:center; margin-bottom:20px;">
    <div style="font-size:32px; margin-bottom:12px;">📧</div>
    <h3 style="font-size:16px; font-weight:700; color:#166534; margin-bottom:8px;">
        Compte créé avec succès !
    </h3>
    <p style="font-size:14px; color:#16a34a; margin-bottom:16px;">
        Un email de vérification a été envoyé à votre adresse.<br>
        Cliquez sur le lien dans l'email pour activer votre compte.
    </p>
    <a href="{{ route('portal.login') }}"
       style="display:inline-block; padding:10px 24px; background:#b13a7e;
              color:#fff; text-decoration:none; border-radius:8px;
              font-size:14px; font-weight:600;">
        Aller à la connexion
    </a>
</div>

            <form method="POST" action="{{ route('portal.register') }}" class="space-y-4">
                @csrf

                {{-- Informations personnelles --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Legal_name</label>
                        <input type="text" name="legal_name" value="{{ old('legal_name') }}"
                               placeholder="BBS_Invest"
                               class="input-focus w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white"
                               required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Trade_name</label>
                        <input type="text" name="trade_name" value="{{ old('trade_name') }}"
                               placeholder="bbs_master_group"
                               class="input-focus w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white"
                               required>
                    </div>
                </div>


                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email professionnel</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           placeholder="contact@pay.sn"
                           class="input-focus w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white"
                           required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           placeholder="+221 77 000 00 00"
                           class="input-focus w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white">
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                      URL de webhook
                  </label>
                  <input type="url" name="webhook_url"
                         value="{{ old('webhook_url') }}"
                         placeholder="https://monsite.sn/webhooks/many"
                         class="input-focus w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white"
                         required>
              </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                    <input type="password" name="password" placeholder="••••••••"
                           class="input-focus w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white"
                           required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
                    <input type="password" name="password_confirmation" placeholder="••••••••"
                           class="input-focus w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white"
                           required>
                </div>

                <div class="flex items-start">
                    <input type="checkbox" name="terms" id="terms"
                           class="mt-1 mr-2 rounded border-gray-300" required>
                    <label for="terms" class="text-sm text-gray-600">
                        J'accepte les
                        <a href="#" class="text-primary hover:underline">conditions d'utilisation</a>
                        et la
                        <a href="#" class="text-primary hover:underline">politique de confidentialité</a>
                    </label>
                </div>

                <button type="submit" class="btn-primary w-full py-2.5 rounded-lg text-sm font-medium">
                    Créer mon compte
                </button>

            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-500">
                    Déjà un compte ?
                    <a href="{{ route('portal.login') }}" class="text-primary hover:underline font-medium">
                        Se connecter
                    </a>
                </p>
            </div>

        </div>
    </div>

</div>
<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();

        const form     = this;
        const formData = new FormData(form);
        const btn      = form.querySelector('button[type="submit"]');

        btn.disabled    = true;
        btn.textContent = 'Création en cours...';

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': formData.get('_token'),
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Afficher message de succès
                document.getElementById('successMessage').style.display = 'block';
                form.reset();
            } else {
                alert(data.message || 'Une erreur est survenue.');
            }
        })
        .catch(() => alert("Une erreur inattendue s'est produite."))
        .finally(() => {
            btn.disabled    = false;
            btn.textContent = 'Créer mon compte';
        });
    });
</script>
</body>
</html>