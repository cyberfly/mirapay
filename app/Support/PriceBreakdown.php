<?php

namespace App\Support;

readonly class PriceBreakdown
{
    public function __construct(
        public int $unitPrice,
        public int $quantity,
        public int $subtotal,
        public int $discount,
        public int $shippingFee,
        public int $total,
    ) {}
}
