<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name', 'AMPUH'),
            'auth' => function () use ($request) {
                $user = $request->user('web');
                $seller = $request->user('seller');

                return [
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->nama,
                        'email' => $user->email,
                        'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    ] : null,
                    'seller' => $seller ? [
                        'id' => $seller->id,
                        'name' => $seller->nama_koperasi,
                        'phone' => $seller->no_hp,
                        'avatar' => $seller->foto_profil ? asset('storage/' . $seller->foto_profil) : null,
                        'can_receive_payments' => $seller->canReceivePayments(),
                    ] : null,
                ];
            },
            'cartCount' => fn () => $request->user('web')?->cart?->items()->sum('quantity') ?? 0,
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
                'status' => $request->session()->get('status'),
            ],
            'chain' => [
                'name' => config('blockchain.base.network_name'),
                'chainId' => (int) config('blockchain.base.chain_id'),
                'explorer' => config('blockchain.base.explorer_url'),
            ],
        ];
    }
}
