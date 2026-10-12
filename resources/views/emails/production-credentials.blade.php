<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f5; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; }
        .header { background: #b13a7e; padding: 32px 40px; text-align: center; }
        .header h1 { color: #fff; font-size: 20px; margin: 0; }
        .body { padding: 40px; }
        .cred { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin: 12px 0; }
        .cred label { font-size: 11px; color: #9ca3af; display: block; margin-bottom: 4px; }
        .cred code { font-size: 13px; color: #111; word-break: break-all; }
        .warning { background: #fef9f0; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #92400e; margin: 16px 0; }
        .btn { display: inline-block; padding: 12px 28px; background: #b13a7e; color: #fff; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>✅ Accès production activé !</h1>
        </div>
        <div class="body">
            <p>Bonjour <strong>{{ $legalName }}</strong>,</p>
            <p>Votre compte Many est maintenant actif en production. Voici vos credentials :</p>

            <div class="cred">
                <label>Email de connexion portail</label>
                <code>{{ $email }}</code>
            </div>
            <div class="cred">
                <label>Mot de passe portail (temporaire)</label>
                <code>{{ $password }}</code>
            </div>
            <div class="cred">
                <label>client_id (Production)</label>
                <code>{{ $clientId }}</code>
            </div>
            <div class="cred">
                <label>client_secret (Production)</label>
                <code>{{ $clientSecret }}</code>
            </div>

            <div class="warning">
                ⚠️ Notez bien votre client_secret. Il ne sera plus jamais affiché.
                Changez votre mot de passe dès votre première connexion.
            </div>

            <div style="text-align:center; margin-top:24px;">
                <a href="{{ $portalUrl }}" class="btn">
                    Accéder au portail →
                </a>
            </div>
        </div>
        <div class="footer">Many Paiement — Ne pas répondre à cet email</div>
    </div>
</body>
</html>