<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Webhooks / IPN untuk Payment Gateways
// Pastikan pengecualian CSRF sudah diatur jika perlu, walau biasanya route API sudah dikecualikan
Route::post('/midtrans/callback', [OrderController::class, 'midtransCallback']);
Route::post('/coinpayments/ipn', [OrderController::class, 'coinpaymentsCallback']);

// Rute Bawaan Sanctum (Opsional jika Anda pakai)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});