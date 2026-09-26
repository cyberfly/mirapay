<?php

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

it('marks pending orders as paid then shipped', function () {
    $product = Product::factory()->physical()->create();
    $order = Order::factory()->forProduct($product)->withAddress()->create();

    Livewire::test('pages::admin.orders.show', ['order' => $order])->call('markAsPaid');
    expect($order->fresh()->status)->toBe(OrderStatus::Paid)->and($order->fresh()->paid_at)->not->toBeNull();

    Livewire::test('pages::admin.orders.show', ['order' => $order->fresh()])->call('markAsShipped');
    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('does not ship digital products', function () {
    $order = Order::factory()->forProduct(Product::factory()->digital()->create())->paid()->create();

    Livewire::test('pages::admin.orders.show', ['order' => $order])->call('markAsShipped');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('does not mark cancelled orders as paid', function () {
    $order = Order::factory()->cancelled()->create();

    Livewire::test('pages::admin.orders.show', ['order' => $order])->call('markAsPaid');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('returns stock and coupon on cancel, only once', function () {
    $product = Product::factory()->withStock(3)->create();
    $coupon = Coupon::factory()->create(['used_count' => 1]);
    $order = Order::factory()->forProduct($product)->withCoupon($coupon)->create(['quantity' => 2]);

    $component = Livewire::test('pages::admin.orders.show', ['order' => $order])->call('cancel');
    expect($product->fresh()->stock)->toBe(5)->and($coupon->fresh()->used_count)->toBe(0);

    $component->call('cancel');
    Livewire::test('pages::admin.orders.show', ['order' => $order->fresh()])->call('cancel');
    expect($product->fresh()->stock)->toBe(5)->and($coupon->fresh()->used_count)->toBe(0);
});

it('does not restore stock for a stale cancel of a shipped order', function () {
    $product = Product::factory()->physical()->withStock(3)->create();
    $order = Order::factory()->forProduct($product)->shipped()->create(['quantity' => 1]);

    Livewire::test('pages::admin.orders.show', ['order' => $order])->call('cancel');

    expect($product->fresh()->stock)->toBe(3)->and($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('filters and searches orders', function () {
    Order::factory()->create(['customer_name' => 'Zainab']);
    Order::factory()->paid()->create(['customer_name' => 'Kamal']);

    Livewire::test('pages::admin.orders.index')
        ->set('status', 'paid')->assertSee('Kamal')->assertDontSee('Zainab')
        ->set('status', '')->set('search', 'Zainab')->assertSee('Zainab')->assertDontSee('Kamal');
});

it('links each order in the list to its detail page', function () {
    $order = Order::factory()->create();

    Livewire::test('pages::admin.orders.index')->assertSee('Lihat')->assertSee(route('admin.orders.show', $order), false);
});
