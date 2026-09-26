<?php

use App\Models\Order;
use App\Models\Product;
use App\Payments\ManualPaymentGateway;
use App\Payments\PaymentGateway;
use Livewire\Livewire;

it('binds the manual gateway by default', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(ManualPaymentGateway::class);
});

it('redirects to the url returned by the gateway', function () {
    app()->instance(PaymentGateway::class, new class implements PaymentGateway
    {
        public function checkoutUrl(Order $order): string
        {
            return 'https://pay.test/'.$order->order_number;
        }

        public function expire(Order $order): void {}
    });

    $product = Product::factory()->digital()->create();

    Livewire::test('pages::shop.product', ['product' => $product])
        ->set(['customer_name' => 'Ali', 'email' => 'a@b.co', 'phone' => '0123456789'])
        ->call('placeOrder')
        ->assertRedirect('https://pay.test/'.Order::first()->order_number);
});
