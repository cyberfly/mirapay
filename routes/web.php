<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::shop.home')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/shop.php';
require __DIR__.'/admin.php';
