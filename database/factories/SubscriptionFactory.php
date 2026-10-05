<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->subscription()->create();

        return [
            'product_id' => $product->id,
            'status' => SubscriptionStatus::Pending,
            'billing_interval' => BillingInterval::Month,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 1,
            'shipping_fee' => 0,
            'requires_shipping' => false,
            'customer_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '0123456789',
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'billing_interval' => $product->billing_interval ?? BillingInterval::Month,
            'requires_shipping' => $product->requires_shipping,
            'shipping_fee' => $product->requires_shipping ? $product->shipping_fee : 0,
        ]);
    }

    /**
     * Paid up, with the current period ending $periodEndsIn days from now (negative = already ended).
     */
    public function active(int $periodEndsIn = 30): static
    {
        return $this->state(function (array $attributes) use ($periodEndsIn) {
            $end = now()->addDays($periodEndsIn);
            $interval = $attributes['billing_interval'];
            $interval = $interval instanceof BillingInterval ? $interval : BillingInterval::from($interval);

            return [
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $interval === BillingInterval::Year ? $end->subYear() : $end->subMonth(),
                'current_period_end' => $end,
                'next_billing_date' => $end->toDateString(),
            ];
        });
    }

    public function pastDue(int $daysAgo = 1): static
    {
        return $this->active(-$daysAgo)->state(fn () => [
            'status' => SubscriptionStatus::PastDue,
            'past_due_since' => now()->subDays($daysAgo),
            'last_reminded_at' => now()->subDays($daysAgo),
        ]);
    }

    public function paused(): static
    {
        return $this->active()->state(fn () => ['status' => SubscriptionStatus::Paused, 'paused_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->active()->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'ends_at' => $attributes['current_period_end'],
        ]);
    }
}
