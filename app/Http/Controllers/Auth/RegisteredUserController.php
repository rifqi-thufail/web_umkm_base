<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): \Inertia\Response
    {
        return \Inertia\Inertia::render('auth/user-register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $nama = $request->input('nama') ?? $request->input('name');
        $request->merge(['nama' => $nama]);

        $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'alamat' => ['nullable', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'nama' => $nama,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'alamat' => $request->input('alamat') ?? '-',
            'no_hp' => $request->input('no_hp') ?? '-',
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
