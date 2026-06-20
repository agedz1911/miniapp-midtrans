<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');


Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/products', 'pages::product')->name('product');
    Route::livewire('/orders/create', 'pages::order.create')->name('orders.create');
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
