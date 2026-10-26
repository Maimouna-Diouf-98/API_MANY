<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f5; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; }
        .header { background: #b13a7e; padding: 32px 40px; text-align: center; }
        .header h1 { color: #fff; font-size: 20px; margin: 0; }
        .body { padding: 40px; color: #111; font-size: 14px; line-height: 1.6; }
        .cred { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin: 12px 0; }
        .cred label { font-size: 11px; color: #9ca3af; display: block; margin-bottom: 4px; }
        .cred code { font-size: 14px; color: #111; word-break: break-all; }
        .warning { background: #fef9f0; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #92400e; margin: 16px 0; }
        .btn { display: inline-block; padding: 12px 28px; background: #b13a7e; color: #fff; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #9ca3af; }
        .small { font-size: 11px; color: #6b7280; word-break: break-all; }
        ol { padding-left: 18px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header"><h1>Bienvenue sur Many</h1></div>
        <div class="body">
            <p>Bonjour <strong>{{ $legalName }}</strong>,</p>
            <p>
                Votre espace agrégateur est créé en <strong>phase de test (sandbox)</strong>.
                Les soldes et les montants y sont fictifs : vous pouvez tester l'API sans risque.
            </p>

            <p><strong>Étape 1 : confirmez votre adresse email</strong></p>
            <p>Vous ne pourrez pas vous connecter tant que votre email n'est pas vérifié.</p>
            <div style="text-align:center; margin: 20px 0;">
                <a href="{{ $verificationUrl }}" class="btn">Vérifier mon email</a>
            </div>
            <p class="small">
                Ce lien est valable {{ $validHours }} heures. S'il ne fonctionne pas, copiez cette adresse dans votre navigateur :<br>
                {{ $verificationUrl }}
            </p>

            <p style="margin-top:24px;"><strong>Étape 2 : connectez-vous</strong></p>
            <div class="cred">
                <label>Email de connexion</label>
                <code>{{ $email }}</code>
            </div>
            <div class="cred">
                <label>Mot de passe temporaire</label>
                <code>{{ $password }}</code>
            </div>
            <div class="warning">
                Changez ce mot de passe dès votre première connexion.
            </div>
            <p style="text-align:center;">
                <a href="{{ $portalUrl }}">{{ $portalUrl }}</a>
            </p>

            <p style="margin-top:24px;"><strong>Ensuite :</strong></p>
            <ol>
                <li>Créez une application : vous obtenez votre <code>client_id</code> et votre <code>client_secret</code> de test.</li>
                <li>Créez un sous-marchand de test et activez-le.</li>
                <li>Testez les collections, décaissements et paiements de masse avec les numéros <code>+221770000001</code> à <code>+221770000099</code>.</li>
                <li>Quand vos tests sont terminés, cliquez sur « Demander l'accès production » dans votre tableau de bord. L'équipe Many étudie votre demande et vous envoie vos accès de production.</li>
            </ol>
        </div>
        <div class="footer">Many Paiement — Ne pas répondre à cet email</div>
    </div>
</body>
</html>
