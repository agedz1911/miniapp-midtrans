<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\MidtransReturnController;

Route::view('/', 'welcome')->name('home');


Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/products', 'pages::product')->name('product');
    Route::livewire('/orders/create', 'pages::order.create')->name('orders.create');
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Midtrans webhook (public, no CSRF)
Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handle'])
    ->name('webhooks.midtrans')
    ->withoutMiddleware(['csrf']);

// Midtrans redirect finish (GET/POST)
Route::match(['get', 'post'], '/order/finish', [MidtransReturnController::class, 'finish'])->name('orders.finish');

require __DIR__.'/settings.php';
