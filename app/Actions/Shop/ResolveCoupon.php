<?php

namespace App\Actions\Shop;

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class ResolveCoupon
{
    public function __invoke(string $code, Product $product): Coupon
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        $error = match (true) {
            $coupon === null => 'Kod kupon tidak wujud.',
            ! $coupon->is_active => 'Kupon ini tidak aktif.',
            $coupon->starts_at?->isFuture() => 'Kupon ini belum bermula.',
            $coupon->expires_at?->isPast() => 'Kupon ini telah tamat tempoh.',
            $coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses => 'Kupon ini telah habis digunakan.',
            $coupon->product_id !== null && $coupon->product_id !== $product->id => 'Kupon ini tidak sah untuk produk ini.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['couponCode' => $error]);
        }

        return $coupon;
    }
}
