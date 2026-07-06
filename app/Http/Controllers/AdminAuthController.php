<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class AdminAuthController extends Controller
{
    /**
     * Show the admin PIN login page.
     */
    public function showLogin()
    {
        // If already authenticated, redirect to dashboard
        if (session('admin_authenticated')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /**
     * Validate the PIN and start an admin session.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $correctPin = config('app.admin_pin', env('ADMIN_PIN', '1234'));

        if ($request->pin === $correctPin) {
            $request->session()->put('admin_authenticated', true);
            $request->session()->put('admin_login_at', now()->toDateTimeString());

            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['pin' => 'PIN incorrecto. Intenta de nuevo.'])->withInput();
    }

    /**
     * Destroy the admin session (logout).
     */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_authenticated');
        $request->session()->forget('admin_login_at');

        return redirect()->route('admin.login')->with('message', 'Sesión cerrada correctamente.');
    }
}
