<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ActivationController extends Controller
{
    /**
     * Tampilkan halaman aktivasi merchant
     */
    public function index(): \Inertia\Response
    {
        $seller = Auth::guard('seller')->user();

        return \Inertia\Inertia::render('seller/activation', [
            'activation' => $seller->getActivationStatus(),
            'config' => [
                'merchant_id' => $seller->merchant_id,
                'client_key' => $seller->client_key,
                // Never send the server key back to the browser.
                'has_server_key' => ! empty($seller->server_key),
            ],
            'isActive' => (bool) $seller->is_active,
        ]);
    }

    /**
     * Update merchant ID dan aktivasi seller
     */
    public function update(Request $request): RedirectResponse
    {
        $seller = Auth::guard('seller')->user();

        $request->validate([
            'merchant_id' => 'required|string|max:255',
            'client_key' => 'required|string|max:255',
            // A stored server key never goes back to the browser, so it may be left empty to keep it.
            'server_key' => [empty($seller->server_key) ? 'required' : 'nullable', 'string', 'max:255'],
        ], [
            'merchant_id.required' => 'Merchant ID wajib diisi.',
            'client_key.required' => 'Client Key wajib diisi.',
            'server_key.required' => 'Server Key wajib diisi.',
        ]);

        $seller->update([
            'merchant_id' => $request->merchant_id,
            'client_key' => $request->client_key,
            'server_key' => $request->filled('server_key') ? $request->server_key : $seller->server_key,
            'is_active' => true,
        ]);

        return redirect()->route('seller.activation.index')
            ->with('success', 'Konfigurasi Midtrans berhasil disimpan dan akun diaktifkan.');
    }

    /**
     * Nonaktifkan seller (opsional)
     */
    public function deactivate(): RedirectResponse
    {
        $seller = Auth::guard('seller')->user();
        $seller->update([
            'is_active' => false,
        ]);

        return redirect()->route('seller.activation.index')
            ->with('warning', 'Akun seller berhasil dinonaktifkan.');
    }
}