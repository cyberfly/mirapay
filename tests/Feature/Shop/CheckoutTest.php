<?php

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function customer(array $overrides = []): array
{
    return array_merge(['customer_name' => 'Ali', 'email' => 'ali@example.com', 'phone' => '0123456789'], $overrides);
}

function address(): array
{
    return ['address_line1' => '1 Jalan Ampang', 'postcode' => '50450', 'city' => 'KL', 'state' => 'kuala-lumpur'];
}

beforeEach(fn () => RateLimiter::clear('place-order:127.0.0.1'));

it('places a digital order without address', function () {
    $product = Product::factory()->digital()->create(['price' => 2000]);

    Livewire::test('pages::shop.product', ['product' => $product])
        ->set(customer(['quantity' => 2] + address()))
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertRedirect();

    $order = Order::firstOrFail();
    expect($order->total)->toBe(4000)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->address_line1)->toBeNull()
        ->and($order->state)->toBeNull();
});

it('places a physical order with shipping and coupon', function () {
    $product = Product::factory()->physical(800)->withStock(5)->create(['price' => 1000]);
    Coupon::factory()->percent(10)->create(['code' => 'RAYA10']);

    Livewire::test('pages::shop.product', ['product' => $product])
        ->set(customer(['quantity' => 2] + address()))
        ->set('couponCode', 'raya10')
        ->call('applyCoupon')
        ->assertSet('appliedCouponCode', 'RAYA10')
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertRedirect(route('shop.orders.show', Order::first() ?? '0'));

    $order = Order::firstOrFail();
    expect($order->total)->toBe(2600)
        ->and($order->discount)->toBe(200)
        ->and($order->state)->toBe('kuala-lumpur')
        ->and($product->fresh()->stock)->toBe(3)
        ->and(Coupon::first()->used_count)->toBe(1);
});

it('validates address for physical products', function () {
    $product = Product::factory()->physical()->create();

    Livewire::test('pages::shop.product', ['product' => $product])
        ->set(customer(['postcode' => '12', 'state' => 'mars']))
        ->call('placeOrder')
        ->assertHasErrors(['address_line1', 'postcode', 'city', 'state']);

    expect(Order::count())->toBe(0);
});

it('validates the phone number', function () {
    $product = Product::factory()->digital()->create();

    Livewire::test('pages::shop.product', ['product' => $product])
        ->set(customer(['phone' => '12345']))
        ->call('placeOrder')
        ->assertHasErrors(['phone']);
});

it('does not allow tampering with locked properties', function () {
    $product = Product::factory()->digital()->create();
    $other = Product::factory()->digital()->create();

    Livewire::test('pages::shop.product', ['product' => $product])
        ->set('product', $other)
        ->assertStatus(500);
})->throws(Exception::class);

it('rejects a coupon that became invalid before submit', function () {
    $product = Product::factory()->digital()->create();
    $coupon = Coupon::factory()->create(['code' => 'ONCE']);

    $component = Livewire::test('pages::shop.product', ['product' => $product])
        ->set('couponCode', 'ONCE')
        ->call('applyCoupon')
        ->assertSet('appliedCouponCode', 'ONCE');

    $coupon->update(['is_active' => false]);

    $component->set(customer())->call('placeOrder')
        ->assertHasErrors(['couponCode'])
        ->assertSet('appliedCouponCode', null);

    expect(Order::count())->toBe(0);
});

it('rate limits order placement', function () {
    $product = Product::factory()->digital()->create();

    $component = Livewire::test('pages::shop.product', ['product' => $product])->set(customer(['phone' => 'x']));

    foreach (range(1, 5) as $i) {
        $component->call('placeOrder');
    }

    $component->set(customer())->call('placeOrder')->assertHasErrors(['customer_name']);
    expect(Order::count())->toBe(0);
});
