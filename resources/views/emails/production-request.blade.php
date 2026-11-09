<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande production</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f5; color: #111; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; }
        .header { background: #b13a7e; padding: 32px 40px; text-align: center; }
        .header h1 { color: #fff; font-size: 22px; margin: 0; }
        .body { padding: 40px; }
        .info { background: #f9fafb; border-radius: 8px; padding: 16px; margin: 20px 0; }
        .info p { margin: 6px 0; font-size: 14px; color: #374151; }
        .btn { display: inline-block; padding: 12px 28px; background: #b13a7e; color: #fff; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9fafb; padding: 20px 40px; text-align: center; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>🚀 Nouvelle demande de production</h1>
        </div>
        <div class="body">
            <p>Un agrégateur souhaite passer en production :</p>
            <div class="info">
                <p><strong>Entreprise :</strong> {{ $aggregator->legal_name }}</p>
                <p><strong>Email :</strong> {{ $aggregator->email }}</p>
                <p><strong>Téléphone :</strong> {{ $aggregator->phone }}</p>
                <p><strong>Webhook :</strong> {{ $aggregator->webhook_url }}</p>
                <p><strong>Date :</strong> {{ now()->format('d/m/Y à H:i') }}</p>
            </div>
            <p>Pour activer ce compte en production, exécutez :</p>
            <div class="info">
                <code>php artisan aggregator:activate-production {{ $aggregator->email }} --commission=1.50</code>
            </div>
        </div>
        <div class="footer">Many Paiement — Administration</div>
    </div>
</body>
</html>