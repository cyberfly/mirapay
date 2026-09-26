<?php

use App\Actions\Shop\CancelOrder;
use App\Actions\Shop\MarkOrderAsPaid;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Payments\PaymentGateway;

it('closes the open payment session when a pending order is cancelled', function () {
    $this->mock(PaymentGateway::class)->shouldReceive('expire')->once()
        ->withArgs(fn (Order $order) => $order->stripe_session_id === 'cs_1');

    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'stripe_session_id' => 'cs_1']);

    expect(app(CancelOrder::class)($order)->status)->toBe(OrderStatus::Cancelled);
});

it('leaves the gateway alone when a paid order is cancelled', function () {
    $this->mock(PaymentGateway::class)->shouldNotReceive('expire');

    $order = Order::factory()->paid()->create(['stripe_session_id' => 'cs_2']);

    expect(app(CancelOrder::class)($order)->status)->toBe(OrderStatus::Cancelled);
});

it('does not close a session for an order that could not be cancelled', function () {
    $this->mock(PaymentGateway::class)->shouldNotReceive('expire');

    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    Order::whereKey($order->id)->update(['status' => OrderStatus::Shipped]);

    app(CancelOrder::class)($order);

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('closes the open payment session when an admin marks an order paid', function () {
    $this->mock(PaymentGateway::class)->shouldReceive('expire')->once();

    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'stripe_session_id' => 'cs_3']);

    expect(app(MarkOrderAsPaid::class)($order)->status)->toBe(OrderStatus::Paid);
});

it('does not close a session when the order was not pending', function () {
    $this->mock(PaymentGateway::class)->shouldNotReceive('expire');

    $order = Order::factory()->paid()->create();

    expect(fn () => app(MarkOrderAsPaid::class)($order))->toThrow(DomainException::class);
});
