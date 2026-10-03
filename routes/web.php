<?php

use App\Http\Controllers\BlockchainController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTransactionController;
use App\Http\Controllers\Seller\ActivationController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\SellerLoginController;
use App\Http\Controllers\SellerPasswordResetController;
use App\Http\Controllers\SellerProfileController;
use App\Http\Controllers\SellerRegisterController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- Publik ---
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::redirect('/home', '/')->name('home.legacy');
Route::redirect('/landing', '/');
Route::get('/products/{product}', [HomeController::class, 'show'])->name('products.show');

// --- Verifikasi & transparansi (Base) ---
Route::get('/verifikasi', [BlockchainController::class, 'verify'])->name('blockchain.verify');
Route::redirect('/blockchain/verify', '/verifikasi');
Route::get('/transparansi', [PublicTransactionController::class, 'index'])->name('public.transactions.index');
Route::get('/transparansi/{hash}', [PublicTransactionController::class, 'show'])->name('public.transactions.show');
Route::redirect('/transparency/transactions', '/transparansi');
Route::get('/api/public/transactions', [PublicTransactionController::class, 'api'])->name('public.transactions.api');

// --- Bantuan ---
Route::get('/bantuan', [HelpController::class, 'index'])->name('help.index');
Route::post('/bantuan', [HelpController::class, 'store'])->name('help.store');

// --- Login pilihan (pembeli / penjual) ---
Route::get('login', fn () => Inertia::render('auth/choose'))->middleware('guest')->name('login');
Route::get('user/login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'create'])->middleware('guest:web')->name('user.login');
Route::get('user/register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'create'])->middleware('guest:web')->name('user.register');

// --- Pembeli ---
Route::middleware('auth:web')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/item/{item}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/item/{item}', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::get('/checkout', [OrderController::class, 'checkout'])->name('orders.checkout');
    Route::post('/orders/process', [OrderController::class, 'process'])->name('orders.process');
    Route::get('/riwayat-pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/riwayat-pesanan/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/send', [WalletController::class, 'send'])->middleware('throttle:wallet-send')->name('wallet.send');
});

// --- Penjual ---
Route::prefix('seller')->name('seller.')->group(function () {
    Route::middleware('guest:seller')->group(function () {
        Route::get('login', [SellerLoginController::class, 'create'])->name('login');
        Route::post('login', [SellerLoginController::class, 'store']);
        Route::get('register', [SellerRegisterController::class, 'create'])->name('register');
        Route::post('register', [SellerRegisterController::class, 'store']);
        Route::get('forgot-password', [SellerPasswordResetController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [SellerPasswordResetController::class, 'store'])->name('password.email');
        Route::get('reset-password', [SellerPasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('reset-password', [SellerPasswordResetController::class, 'update'])->name('password.store');
    });

    Route::middleware('auth:seller')->group(function () {
        Route::get('/dashboard', [SellerDashboardController::class, 'index'])->name('dashboard');
        Route::post('logout', [SellerLoginController::class, 'destroy'])->name('logout');

        Route::get('/activation', [ActivationController::class, 'index'])->name('activation.index');
        Route::put('/activation', [ActivationController::class, 'update'])->name('activation.update');
        Route::patch('/activation/deactivate', [ActivationController::class, 'deactivate'])->name('activation.deactivate');

        Route::resource('products', ProductController::class)->except('show');
        Route::post('products/{product}/images/{image}/set-primary', [ProductController::class, 'setPrimaryImage'])->name('products.images.set-primary');
        Route::delete('products/{product}/images/{image}', [ProductController::class, 'deleteImage'])->name('products.images.delete');

        Route::get('/profile', [SellerProfileController::class, 'edit'])->name('profile.edit');
        Route::post('/profile', [SellerProfileController::class, 'update'])->name('profile.update');

        Route::middleware('seller.active')->group(function () {
            Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
        });
    });
});

// Profil toko publik — harus setelah rute seller yang terautentikasi
Route::get('/seller/{seller}', [SellerProfileController::class, 'show'])->name('seller.profile.show');

require __DIR__.'/auth.php';
