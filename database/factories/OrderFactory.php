<?php

namespace Database\Factories;

use App\Enums\MalaysianState;
use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();
        $quantity = fake()->numberBetween(1, 3);
        $subtotal = $product->price * $quantity;

        return [
            'order_number' => 'MP'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
            'product_id' => $product->id,
            'status' => OrderStatus::Pending,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => $subtotal,
            'requires_shipping' => false,
            'customer_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '0123456789',
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(function () use ($product) {
            $quantity = fake()->numberBetween(1, 3);
            $shipping = $product->requires_shipping ? $product->shipping_fee : 0;

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => $quantity,
                'subtotal' => $product->price * $quantity,
                'shipping_fee' => $shipping,
                'total' => $product->price * $quantity + $shipping,
                'requires_shipping' => $product->requires_shipping,
            ];
        });
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Paid, 'paid_at' => now()]);
    }

    public function shipped(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Shipped, 'paid_at' => now(), 'shipped_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
    }

    public function withAddress(): static
    {
        return $this->state(fn () => [
            'requires_shipping' => true,
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'postcode' => '50450',
            'city' => 'Kuala Lumpur',
            'state' => MalaysianState::KualaLumpur->value,
        ]);
    }

    public function withCoupon(?Coupon $coupon = null): static
    {
        return $this->state(function () use ($coupon) {
            $coupon ??= Coupon::factory()->create();

            return ['coupon_id' => $coupon->id, 'coupon_code' => $coupon->code];
        });
    }
}
