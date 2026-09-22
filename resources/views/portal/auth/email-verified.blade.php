<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email vérifié — Many</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Instrument Sans', sans-serif;
            background: #f4f4f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 48px 40px;
            text-align: center;
            max-width: 640px;
            width: 90%;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .icon {
            width: 72px;
            height: 72px;
            background: #f0fdf4;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #111;
            margin-bottom: 12px;
        }
        p {
            font-size: 15px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .btn {
            display: inline-block;
            padding: 13px 32px;
            background: #b13a7e;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn:hover { background: #8e2e64; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>Email vérifié avec succès !</h1>
        <p>
            Votre adresse email a été confirmée.
            Vous pouvez maintenant vous connecter
            à votre espace agrégateur Many.
        </p>
        <a href="{{ route('portal.login') }}" class="btn">
            Se connecter
        </a>
    </div>
</body>
</html>