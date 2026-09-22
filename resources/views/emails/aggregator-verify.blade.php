<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification email — Many</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: #f4f4f5;
            color: #111;
        }
        .wrapper {
            max-width: 560px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .header {
            background: #b13a7e;
            padding: 36px 40px;
            text-align: center;
        }
        .header .logo {
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }
        .header p {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            margin-top: 6px;
        }
        .body {
            padding: 40px;
        }
        .body h1 {
            font-size: 22px;
            font-weight: 700;
            color: #111;
            margin-bottom: 12px;
        }
        .body p {
            font-size: 15px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .btn-wrapper {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            padding: 14px 36px;
            background: #b13a7e;
            color: #fff !important;
            text-decoration: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
        }
        .divider {
            border: none;
            border-top: 1px solid #f0f0f0;
            margin: 28px 0;
        }
        .url-fallback {
            font-size: 12px;
            color: #9ca3af;
            word-break: break-all;
            line-height: 1.6;
        }
        .url-fallback a {
            color: #b13a7e;
        }
        .expiry {
            background: #fef9f0;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #92400e;
            margin-bottom: 20px;
        }
        .footer {
            background: #f9fafb;
            padding: 24px 40px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #f0f0f0;
        }
        .footer strong { color: #6b7280; }
    </style>
</head>
<body>
    <div class="wrapper">

        <div class="header">
            <div class="logo">Many</div>
            <p>Portail Agrégateur</p>
        </div>

        <div class="body">
            <h1>Bonjour {{ $aggregator->legal_name }} 👋🏾</h1>

            <p>
                Merci de vous être inscrit sur le portail agrégateur Many.
                Pour activer votre compte et accéder à votre espace,
                veuillez vérifier votre adresse email en cliquant
                sur le bouton ci-dessous.
            </p>

            <div class="expiry">
                ⏳ Ce lien est valable <strong>24 heures</strong>.
                Après ce délai, vous devrez en demander un nouveau.
            </div>

            <div class="btn-wrapper">
                <a href="{{ $verificationUrl }}" class="btn">
                    Vérifier mon adresse email
                </a>
            </div>

            <hr class="divider">

            <p class="url-fallback">
                Si le bouton ne fonctionne pas, copiez et collez
                ce lien dans votre navigateur :<br>
                <a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>
            </p>

            <hr class="divider">

            <p style="font-size:13px; color:#9ca3af;">
                Si vous n'avez pas créé de compte sur Many,
                ignorez cet email.
            </p>
        </div>

        <div class="footer">
            <strong>Many Paiement</strong><br>
            Cet email a été envoyé automatiquement, merci de ne pas y répondre.
        </div>

    </div>
</body>
</html>