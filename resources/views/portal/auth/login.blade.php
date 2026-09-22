<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Many — Connexion Portail</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { font-family: 'Instrument Sans', sans-serif; background: #fff; }

        .login-page { display: flex; min-height: 100vh; }

        /* Panneau gauche */
        .left-login {
            width: 50%;
            padding: 60px 64px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo { margin-bottom: 40px; }
        .logo img { height: 36px; }
        .logo-text {
            font-size: 28px;
            font-weight: 800;
            color: #b13a7e;
            letter-spacing: -0.5px;
        }

        .main-heading {
            font-size: 28px;
            font-weight: 700;
            color: #111;
            margin-bottom: 10px;
        }

        .main-text {
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 36px;
        }

        .form-group { margin-bottom: 20px; }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .input-grp {
            position: relative;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            background: #fafafa;
            transition: border-color 0.2s;
        }

        .input-grp:focus-within {
            border-color: #b13a7e;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(177, 58, 126, 0.12);
        }

        .input-grp input {
            width: 100%;
            padding: 13px 16px;
            border: none;
            background: transparent;
            font-size: 14px;
            color: #111;
            outline: none;
            border-radius: 10px;
        }

        .input-grp input::placeholder { color: #9ca3af; }

        .input-grp.password-grp input { padding-right: 48px; }

        .password-eye {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .eye-icon { color: #9ca3af; transition: color 0.2s; }
        .eye-icon:hover { color: #b13a7e; }

        .input-grp.input-error { border-color: #ef4444 !important; }

        #errorContainer {
            display: none;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
            margin-bottom: 16px;
        }

        .primary-cta {
            width: 100%;
            padding: 13px;
            background: #b13a7e;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }

        .primary-cta:hover { background: #8e2e64; }
        .primary-cta:disabled {
            background: #e5bbd4;
            cursor: not-allowed;
        }

        .forgot-pass {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
        }

        .forgot-pass a { color: #b13a7e; text-decoration: none; font-weight: 500; }
        .forgot-pass a:hover { text-decoration: underline; }

        .register-link {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
        }

        .register-link a { color: #b13a7e; font-weight: 600; text-decoration: none; }
        .register-link a:hover { text-decoration: underline; }

        /* Panneau droit */
        .right-login {
            width: 50%;
            background: #b13a7e;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .right-bg-color {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Formes décoratives */
        .shape-1 {
            position: absolute;
            top: -80px;
            right: -80px;
            width: 320px;
            height: 320px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }

        .shape-2 {
            position: absolute;
            bottom: -100px;
            left: -60px;
            width: 280px;
            height: 280px;
            background: rgba(0,0,0,0.08);
            border-radius: 50%;
        }

        .shape-3 {
            position: absolute;
            top: 40%;
            left: -40px;
            width: 160px;
            height: 160px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .login-inner-data {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 48px;
        }

        .small-logo {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.15);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            font-size: 28px;
            font-weight: 800;
            color: #fff;
        }

        .login-inner-data h2 {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 14px;
            line-height: 1.3;
        }

        .login-inner-data p {
            font-size: 15px;
            color: rgba(255,255,255,0.75);
            line-height: 1.6;
            max-width: 320px;
            margin: 0 auto;
        }

        .features {
            margin-top: 36px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: left;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255,255,255,0.85);
            font-size: 14px;
        }

        .feature-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.6);
            flex-shrink: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .right-login { display: none; }
            .left-login { width: 100%; padding: 40px 24px; }
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 50;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            max-width: 360px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .modal-icon {
            width: 64px;
            height: 64px;
            background: #f0fdf4;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
        }
        .modal-box h2 {
            font-size: 20px;
            font-weight: 700;
            color: #111;
            margin-bottom: 8px;
        }
        .modal-box p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 24px;
        }
        .modal-btn {
            width: 100%;
            padding: 12px;
            background: #b13a7e;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .modal-btn:hover { background: #8e2e64; }
    </style>
</head>
<body>

<section>
    <div class="login-page">

        {{-- Panneau gauche --}}
        <div class="left-login">
            <div class="logo">
                <span class="logo-text">Many</span>
                
            </div>

            <h1 class="main-heading">Bienvenu à Many 👋🏾</h1>
            <p class="main-text">Saisissez vos informations pour vous connecter.</p>

            <div id="errorContainer"></div>

            <form id="loginForm" method="POST" action="{{ route('portal.login') }}">
                @csrf

                <div class="form-group">
                    <label>Adresse email</label>
                    <div class="input-grp" id="emailGroup">
                        <input
                            type="email"
                            name="email"
                            placeholder="Saisissez votre adresse e-mail"
                            value="{{ old('email') }}"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mot de passe</label>
                    <div class="input-grp password-grp" id="passwordGroup">
                        <input
                            type="password"
                            name="password"
                            id="passwordInput"
                            placeholder="Entrez votre mot de passe"
                            required>
                        <div class="password-eye" onclick="togglePassword()">
                            <svg id="eyeIcon" class="eye-icon" width="20" height="20"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <input
                    type="submit"
                    value="Login"
                    class="primary-cta"
                    id="submitBtn"
                    disabled>
                <div id="resendSection" style="display:none; margin-top:16px; text-align:center;">
    <p style="font-size:13px; color:#6b7280; margin-bottom:8px;">
        Vous n'avez pas reçu l'email ?
    </p>
    <button type="button" onclick="resendVerification()"
            style="font-size:13px; color:#b13a7e; background:none;
                   border:none; cursor:pointer; text-decoration:underline;">
        Renvoyer l'email de vérification
    </button>
</div>

                <div class="forgot-pass">
                    <span>Mot de passe oublié ? </span>
                    <a href="#">Réinitialiser le mot de passe</a>
                </div>
            </form>

            <div class="register-link">
                Pas encore de compte ?
                <a href="{{ route('portal.register') }}">Créer un compte</a>
            </div>
        </div>

        {{-- Panneau droit --}}
        <div class="right-login">
            <div class="right-bg-color">
                <div class="shape-1"></div>
                <div class="shape-2"></div>
                <div class="shape-3"></div>

                <div class="login-inner-data">
                    
                    <h2>Facilitez vos transactions</h2>
                    <p>Connexion sécurisée à votre environnement de paiement via le portail agrégateur Many.</p>

                    <div class="features">
                        <div class="feature-item">
                            <div class="feature-dot"></div>
                            Accès sandbox immédiat
                        </div>
                        <div class="feature-item">
                            <div class="feature-dot"></div>
                            Clés API générées automatiquement
                        </div>
                        <div class="feature-item">
                            <div class="feature-dot"></div>
                            1 000 000 XOF virtuel pour vos tests
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

{{-- Modal succès --}}
<div class="modal-overlay" id="loginModal">
    <div class="modal-box">
        <div class="modal-icon">🎉</div>
        <h2>Connexion réussie ! 👏🏾</h2>
        <p>Vous serez redirigé vers votre tableau de bord Many.</p>
        <button class="modal-btn" id="okayBtn">D'accord</button>
    </div>
</div>

<script>
    const emailInput  = document.querySelector('input[name="email"]');
    const passInput   = document.getElementById('passwordInput');
    const submitBtn   = document.getElementById('submitBtn');
    const errorBox    = document.getElementById('errorContainer');
    const emailGroup  = document.getElementById('emailGroup');
    const passGroup   = document.getElementById('passwordGroup');

    let redirectUrl = '';

    function checkValidity() {
        const email    = emailInput.value.trim();
        const password = passInput.value;
        const emailOk  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        submitBtn.disabled = !(emailOk && password.length >= 6);
    }

    function showError(message) {
        emailGroup.classList.add('input-error');
        passGroup.classList.add('input-error');
        errorBox.textContent = message;
        errorBox.style.display = 'block';
    }

    function clearError() {
        emailGroup.classList.remove('input-error');
        passGroup.classList.remove('input-error');
        errorBox.style.display = 'none';
    }

    function togglePassword() {
        const input = document.getElementById('passwordInput');
        const icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>`;
        } else {
            input.type = 'password';
            icon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
        }
    }

    emailInput.addEventListener('input', () => { clearError(); checkValidity(); });
    passInput.addEventListener('input',  () => { clearError(); checkValidity(); });

    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        clearError();
        submitBtn.disabled = true;
        submitBtn.value = 'Connexion...';

        const formData = new FormData(this);

        fetch("{{ route('portal.login') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ?
                    document.querySelector('meta[name="csrf-token"]').content :
                    formData.get('_token'),
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
              redirectUrl = data.redirect_url;
              document.getElementById('loginModal').classList.add('active');
          } else if (data.unverified) {
              showError(data.message);
              // Afficher bouton de renvoi
              document.getElementById('resendSection').style.display = 'block';
          } else {
              showError(data.message || 'Identifiants incorrects.');
          }
       })
        .catch(() => showError("Une erreur inattendue s'est produite."))
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.value = 'Login';
            checkValidity();
        });
    });

    document.getElementById('okayBtn').addEventListener('click', function() {
        this.textContent = 'Veuillez patienter...';
        this.disabled = true;
        setTimeout(() => window.location.href = redirectUrl, 800);
    });
    
    function resendVerification() {
    fetch("{{ route('portal.resend.verification') }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email: emailInput.value
        })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
    });
}
</script>

</body>
</html>