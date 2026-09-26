<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Payments\ManualPaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\StripePaymentGateway;

beforeEach(fn () => config(['services.stripe.webhook_secret' => 'whsec_test']));

function stripeEvent(string $type, array $object): array
{
    $payload = json_encode([
        'id' => 'evt_1', 'object' => 'event', 'type' => $type,
        'data' => ['object' => $object + ['object' => 'checkout.session']],
    ]);
    $t = time();
    $sig = hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test');

    return [$payload, ['HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}", 'CONTENT_TYPE' => 'application/json']];
}

function postStripe(string $type, array $object)
{
    [$payload, $server] = stripeEvent($type, $object);

    return test()->call('POST', route('stripe.webhook'), [], [], [], $server, $payload);
}

it('marks the order as paid on completed checkout', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'stripe_session_id' => 'cs_1']);

    postStripe('checkout.session.completed', ['id' => 'cs_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1'])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_reference)->toBe('pi_1');

    postStripe('checkout.session.completed', ['id' => 'cs_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1'])->assertOk();
    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('cancels the order and restores stock when the session expires', function () {
    $product = Product::factory()->create(['stock' => 4]);
    $order = Order::factory()->create([
        'product_id' => $product->id, 'quantity' => 2, 'status' => OrderStatus::Pending, 'stripe_session_id' => 'cs_2',
    ]);

    postStripe('checkout.session.expired', ['id' => 'cs_2'])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->refresh()->stock)->toBe(6);
});

it('rejects an invalid signature', function () {
    $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad'], '{}')
        ->assertStatus(400);
});

it('selects the gateway from config', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(ManualPaymentGateway::class);

    config(['services.payment_gateway' => 'stripe']);
    expect(app(PaymentGateway::class))->toBeInstanceOf(StripePaymentGateway::class);
});
