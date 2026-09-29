<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SellerLoginController extends Controller
{
    /**
     * Menampilkan form login untuk seller.
     */
    public function create(): \Inertia\Response
    {
        return \Inertia\Inertia::render('seller-auth/login', ['status' => session('status')]);
    }

    /**
     * Menangani permintaan login dari seller.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'no_hp' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Coba otentikasi menggunakan guard 'seller'
        if (! Auth::guard('seller')->attempt($request->only('no_hp', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'no_hp' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        // Arahkan ke dashboard seller setelah login berhasil
        return redirect()->intended(route('seller.dashboard'));
    }

    /**
     * Menangani permintaan logout dari seller.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('seller')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

