<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 99999),
            'tagline' => fake()->sentence(),
            'description' => '<h2>Tentang produk</h2><p>'.fake()->paragraph().'</p>',
            'image_path' => null,
            'price' => fake()->numberBetween(1000, 30000),
            'compare_at_price' => null,
            'requires_shipping' => false,
            'shipping_fee' => 0,
            'stock' => null,
            'is_active' => true,
            'is_featured' => false,
            'category_id' => null,
        ];
    }

    public function physical(int $shippingFee = 800): static
    {
        return $this->state(fn () => ['requires_shipping' => true, 'shipping_fee' => $shippingFee]);
    }

    public function digital(): static
    {
        return $this->state(fn () => ['requires_shipping' => false, 'shipping_fee' => 0]);
    }

    public function subscription(BillingInterval $interval = BillingInterval::Month): static
    {
        return $this->state(fn () => ['billing_interval' => $interval]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function soldOut(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    public function withStock(int $stock = 10): static
    {
        return $this->state(fn () => ['stock' => $stock]);
    }

    public function onSale(): static
    {
        return $this->state(fn (array $attributes) => ['compare_at_price' => (int) ($attributes['price'] * 1.5)]);
    }
}
