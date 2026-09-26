<?php

use Illuminate\Support\Facades\Route;

Route::livewire('produk', 'pages::shop.products')->name('shop.products.index');
Route::livewire('p/{product:slug}', 'pages::shop.product')->name('shop.products.show');
Route::livewire('pesanan/{order:public_id}', 'pages::shop.order')->name('shop.orders.show');
