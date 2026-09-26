<?php

use App\Actions\Shop\CalculatePrice;
use App\Models\Coupon;
use App\Models\Product;

it('calculates totals with a percent coupon rounded half up', function () {
    $product = Product::factory()->physical(800)->make(['price' => 1999]);
    $coupon = Coupon::factory()->percent(10)->make();

    $result = (new CalculatePrice)($product, 3, $coupon);

    expect($result->subtotal)->toBe(5997)
        ->and($result->discount)->toBe(600)
        ->and($result->shippingFee)->toBe(800)
        ->and($result->total)->toBe(6197);
});

it('caps a fixed discount at the subtotal and never discounts shipping', function () {
    $product = Product::factory()->physical(800)->make(['price' => 300]);
    $coupon = Coupon::factory()->fixed(5000)->make();

    $result = (new CalculatePrice)($product, 1, $coupon);

    expect($result->discount)->toBe(300)->and($result->total)->toBe(800);
});

it('charges no shipping for digital products', function () {
    $product = Product::factory()->digital()->make(['price' => 1000, 'shipping_fee' => 900]);

    expect((new CalculatePrice)($product, 2)->total)->toBe(2000);
});
