<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Payment gateway webhooks: outside the web group, so no session, CSRF or staging gate.
Route::post('/midtrans/callback', [OrderController::class, 'midtransCallback'])->middleware('throttle:120,1')->name('midtrans.callback');
Route::post('/coinpayments/ipn', [OrderController::class, 'coinpaymentsCallback'])->middleware('throttle:120,1')->name('coinpayments.ipn');
