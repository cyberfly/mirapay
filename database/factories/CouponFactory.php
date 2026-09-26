<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'type' => CouponType::Percent,
            'value' => 10,
            'product_id' => null,
            'max_uses' => null,
            'used_count' => 0,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    public function percent(int $value = 10): static
    {
        return $this->state(fn () => ['type' => CouponType::Percent, 'value' => $value]);
    }

    public function fixed(int $sen = 500): static
    {
        return $this->state(fn () => ['type' => CouponType::Fixed, 'value' => $sen]);
    }

    public function forProduct(?Product $product = null): static
    {
        return $this->state(fn () => ['product_id' => $product?->id ?? Product::factory()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function notStarted(): static
    {
        return $this->state(fn () => ['starts_at' => now()->addDay()]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => ['max_uses' => 5, 'used_count' => 5]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
