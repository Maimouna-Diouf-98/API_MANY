<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $guard = Auth::guard('admin');

        if (!$guard->attempt($credentials)) {
            return back()->withInput($request->only('email'))
                         ->withErrors(['email' => 'Identifiants incorrects.']);
        }

        $admin = $guard->user();

        // Même message qu'un mauvais mot de passe : on ne révèle pas l'état du compte
        if (!$admin->isActive() || !$admin->canAccessDashboard()) {
            $guard->logout();

            return back()->withInput($request->only('email'))
                         ->withErrors(['email' => "Ce compte n'a pas accès à l'espace admin."]);
        }

        $request->session()->regenerate();

        if (Schema::connection('mysql_money')->hasColumn('admins', 'last_login_at')) {
            DB::connection('mysql_money')->table('admins')
              ->where('id', $admin->id)
              ->update(['last_login_at' => now()]);
        }

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
