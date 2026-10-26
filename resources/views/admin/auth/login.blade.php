<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion admin — Many</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <form method="POST" action="{{ route('admin.login.submit') }}"
          class="w-full max-w-sm bg-white rounded-xl border border-gray-200 p-8 shadow-sm">
        @csrf
        <h1 class="text-lg font-bold mb-1" style="color:#b13a7e;">Many · Administration</h1>
        <p class="text-sm text-gray-400 mb-6">Accès réservé à l'équipe Many.</p>

        @error('email')
            <div class="mb-4 px-3 py-2 rounded-lg bg-red-50 text-sm text-red-700">{{ $message }}</div>
        @enderror

        <label class="block text-xs text-gray-500 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm mb-4">

        <label class="block text-xs text-gray-500 mb-1">Mot de passe</label>
        <input type="password" name="password" required
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm mb-6">

        <button class="w-full py-2.5 rounded-lg text-sm font-medium text-white"
                style="background-color:#b13a7e;">
            Se connecter
        </button>
    </form>
</body>
</html>
