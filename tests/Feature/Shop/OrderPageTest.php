<?php

use App\Models\Order;

it('shows an order by public ulid', function () {
    $order = Order::factory()->create();

    $this->get(route('shop.orders.show', $order))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Pembayaran belum diterima')
        ->assertSee('noindex', false);
});

it('does not expose orders by numeric id', function () {
    $order = Order::factory()->create();

    $this->get('/pesanan/'.$order->id)->assertNotFound();
});
