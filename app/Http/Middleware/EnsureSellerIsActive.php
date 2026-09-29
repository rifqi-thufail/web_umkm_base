<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('seller')->check()) {
            $seller = Auth::guard('seller')->user();
            
            // List route yang memerlukan aktivasi penuh (untuk pembayaran)
            $paymentRequiredRoutes = [
                'seller.orders.*',
                'seller.earnings.*',
                'seller.payouts.*'
            ];
            
            // Cek apakah route saat ini memerlukan aktivasi penuh
            $requiresFullActivation = false;
            foreach ($paymentRequiredRoutes as $pattern) {
                if ($request->routeIs($pattern)) {
                    $requiresFullActivation = true;
                    break;
                }
            }
            
            // Jika route memerlukan aktivasi penuh dan seller belum aktif
            if ($requiresFullActivation && !$seller->isActivated() && !$request->routeIs('seller.activation.*')) {
                return redirect()->route('seller.activation.index')
                    ->with('warning', 'Fitur ini memerlukan aktivasi akun penuh. Silakan lengkapi konfigurasi Midtrans.');
            }
        }

        return $next($request);
    }
}