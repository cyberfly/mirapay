<?php

use App\Actions\Shop\PlaceOrder;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

function place(Product $product, int $qty, ?string $coupon = null): Order
{
    return app(PlaceOrder::class)($product, $qty, $coupon, ['customer_name' => 'A', 'email' => 'a@b.co', 'phone' => '0123456789']);
}

it('prevents overselling even with a stale product instance', function () {
    $product = Product::factory()->withStock(2)->create();
    $stale = Product::find($product->id);

    place($product, 2);

    expect(fn () => place($stale, 1))->toThrow(ValidationException::class, 'Stok tidak mencukupi');
    expect($product->fresh()->stock)->toBe(0)->and(Order::count())->toBe(1);
});

it('prevents exceeding coupon max uses', function () {
    $product = Product::factory()->create();
    $coupon = Coupon::factory()->create(['code' => 'ONE', 'max_uses' => 1]);
    $stale = Coupon::find($coupon->id);

    place($product, 1, 'ONE');
    $stale->forceFill(['used_count' => 0]);

    expect(fn () => place($product, 1, 'ONE'))->toThrow(ValidationException::class);
    expect($coupon->fresh()->used_count)->toBe(1)->and(Order::count())->toBe(1);
});

it('rejects inactive products', function () {
    place(Product::factory()->inactive()->create(), 1);
})->throws(ValidationException::class);
