<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
        }

        if (! $request->user()->active) {
            Auth::logout();
            return back()->withErrors(['email' => 'Ce compte est désactivé.']);
        }

        $request->session()->regenerate();

        return match ($request->user()->role) {
            UserRole::CASHIER => redirect()->route('cash.index'),
            UserRole::STOCKKEEPER => redirect()->route('stock.index'),
            default => redirect()->intended(route('dashboard')),
        };
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
