<?php

use App\Http\Controllers\Admin\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::livewire('products', 'pages::admin.products.index')->name('products.index');
        Route::livewire('products/create', 'pages::admin.products.create')->name('products.create');
        Route::livewire('products/{product}/edit', 'pages::admin.products.edit')->name('products.edit');

        Route::livewire('categories', 'pages::admin.categories.index')->name('categories.index');

        Route::livewire('coupons', 'pages::admin.coupons.index')->name('coupons.index');
        Route::livewire('coupons/create', 'pages::admin.coupons.create')->name('coupons.create');
        Route::livewire('coupons/{coupon}/edit', 'pages::admin.coupons.edit')->name('coupons.edit');

        Route::livewire('orders', 'pages::admin.orders.index')->name('orders.index');
        Route::livewire('orders/{order:public_id}', 'pages::admin.orders.show')->name('orders.show');

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::livewire('sales', 'pages::admin.reports.sales')->name('sales');
            Route::livewire('products', 'pages::admin.reports.products')->name('products');
            Route::livewire('coupons', 'pages::admin.reports.coupons')->name('coupons');
            Route::livewire('customers', 'pages::admin.reports.customers')->name('customers');
            Route::livewire('statuses', 'pages::admin.reports.statuses')->name('statuses');
            Route::get('{report}/export', ReportExportController::class)->whereIn('report', ['sales', 'products', 'coupons', 'customers', 'statuses'])->name('export');
        });
    });
