<?php

namespace App\Actions\Shop;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Product;
use App\Support\PriceBreakdown;

class CalculatePrice
{
    public function __invoke(Product $product, int $quantity, ?Coupon $coupon = null): PriceBreakdown
    {
        $subtotal = $product->price * $quantity;

        $discount = match ($coupon?->type) {
            CouponType::Percent => intdiv($subtotal * $coupon->value + 50, 100),
            CouponType::Fixed => $coupon->value,
            default => 0,
        };
        $discount = min($discount, $subtotal);

        $shippingFee = $product->requires_shipping ? $product->shipping_fee : 0;

        return new PriceBreakdown(
            unitPrice: $product->price,
            quantity: $quantity,
            subtotal: $subtotal,
            discount: $discount,
            shippingFee: $shippingFee,
            total: $subtotal - $discount + $shippingFee,
        );
    }
}
