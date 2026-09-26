<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

function adminRoutes(): array
{
    return [
        'products index' => [fn () => route('admin.products.index')],
        'products create' => [fn () => route('admin.products.create')],
        'products edit' => [fn () => route('admin.products.edit', Product::factory()->create())],
        'coupons index' => [fn () => route('admin.coupons.index')],
        'coupons create' => [fn () => route('admin.coupons.create')],
        'coupons edit' => [fn () => route('admin.coupons.edit', Coupon::factory()->create())],
        'orders index' => [fn () => route('admin.orders.index')],
        'orders show' => [fn () => route('admin.orders.show', Order::factory()->create())],
    ];
}

it('redirects guests', function (Closure $url) {
    $this->get($url())->assertRedirect(route('login'));
})->with(adminRoutes());

it('forbids non-admins', function (Closure $url) {
    $this->actingAs(User::factory()->create())->get($url())->assertForbidden();
})->with(adminRoutes());

it('allows admins', function (Closure $url) {
    $this->actingAs(User::factory()->admin()->create())->get($url())->assertOk();
})->with(adminRoutes());

it('shows the shop menu only to admins', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertDontSee('Kedai');
    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertSee('Kedai');
});

it('does not allow mass assigning is_admin', function () {
    $user = User::create(['name' => 'X', 'email' => 'x@y.co', 'password' => 'secret123', 'is_admin' => true]);

    expect($user->fresh()->is_admin)->toBeFalse();
});
