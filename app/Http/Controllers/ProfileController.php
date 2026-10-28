<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): \Inertia\Response
    {
        $user = $request->user();

        return \Inertia\Inertia::render('buyer/profile', [
            'profile' => [
                'name' => $user->nama,
                'email' => $user->email,
                'phone' => $user->no_hp !== '-' ? $user->no_hp : '',
                'address' => $user->alamat !== '-' ? $user->alamat : '',
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        unset($validated['avatar']);
        // Columns are NOT NULL; '-' is the existing "not filled in" marker.
        $validated['no_hp'] = ($validated['no_hp'] ?? null) ?: '-';
        $validated['alamat'] = ($validated['alamat'] ?? null) ?: '-';
        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('success', 'Profil disimpan.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        if ($user->orders()->exists()) {
            // Orders cascade on delete, and paid ones are the seller's books and anchored on Base.
            // Keep the row (and its id, which is in the fingerprint) but erase everything personal.
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->cart()->delete();
            $user->wishlists()->delete();
            $user->forceFill([
                'nama' => 'Akun dihapus',
                'email' => 'deleted-' . $user->id . '-' . \Illuminate\Support\Str::random(12) . '@deleted.invalid',
                'password' => \Illuminate\Support\Str::random(64),
                'no_hp' => '-',
                'alamat' => '-',
                'avatar' => null,
                'email_verified_at' => null,
                'remember_token' => null,
            ])->save();
        } else {
            $user->delete();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}