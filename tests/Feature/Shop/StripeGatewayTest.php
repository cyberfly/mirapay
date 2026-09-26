<?php

use App\Models\Order;
use App\Payments\StripePaymentGateway;
use Tests\Support\FakeStripeHttpClient;

function checkoutSession(string $id, string $status = 'open'): array
{
    return [
        'id' => $id,
        'object' => 'checkout.session',
        'status' => $status,
        'url' => $status === 'open' ? "https://checkout.stripe.test/{$id}" : null,
    ];
}

beforeEach(function () {
    $this->stripe = FakeStripeHttpClient::install();
    $this->gateway = app()->make(StripePaymentGateway::class);
});

afterEach(fn () => FakeStripeHttpClient::uninstall());

it('creates a session tagged with the order and remembers it', function () {
    $order = Order::factory()->create();
    $this->stripe->respond('POST', '/v1/checkout/sessions', checkoutSession('cs_new'));

    expect($this->gateway->checkoutUrl($order))->toBe('https://checkout.stripe.test/cs_new')
        ->and($order->fresh()->stripe_session_id)->toBe('cs_new')
        ->and($order->fresh()->payment_gateway)->toBe('stripe');

    $params = $this->stripe->requestsTo('POST', '/v1/checkout/sessions')[0]['params'];
    expect($params['client_reference_id'])->toBe($order->public_id)
        ->and($params['line_items'][0]['price_data']['unit_amount'])->toBe($order->total);
});

it('reuses the still-open session instead of creating a second payable one', function () {
    $order = Order::factory()->create(['stripe_session_id' => 'cs_open']);
    $this->stripe->respond('GET', '/v1/checkout/sessions/cs_open', checkoutSession('cs_open'));

    expect($this->gateway->checkoutUrl($order))->toBe('https://checkout.stripe.test/cs_open')
        ->and($this->stripe->requestsTo('POST', '/v1/checkout/sessions'))->toBeEmpty()
        ->and($order->fresh()->stripe_session_id)->toBe('cs_open');
});

it('creates a fresh session once the previous one has expired', function () {
    $order = Order::factory()->create(['stripe_session_id' => 'cs_old']);
    $this->stripe
        ->respond('GET', '/v1/checkout/sessions/cs_old', checkoutSession('cs_old', 'expired'))
        ->respond('POST', '/v1/checkout/sessions', checkoutSession('cs_fresh'));

    expect($this->gateway->checkoutUrl($order))->toBe('https://checkout.stripe.test/cs_fresh')
        ->and($order->fresh()->stripe_session_id)->toBe('cs_fresh');
});

it('does not offer a second payment when the previous session was already paid', function () {
    $order = Order::factory()->create(['stripe_session_id' => 'cs_paid']);
    $this->stripe->respond('GET', '/v1/checkout/sessions/cs_paid', checkoutSession('cs_paid', 'complete'));

    expect($this->gateway->checkoutUrl($order))->toBe(route('shop.orders.show', $order))
        ->and($this->stripe->requestsTo('POST', '/v1/checkout/sessions'))->toBeEmpty()
        ->and($order->fresh()->stripe_session_id)->toBe('cs_paid');
});

it('expires the open session of an order', function () {
    $order = Order::factory()->create(['stripe_session_id' => 'cs_open']);
    $this->stripe->respond('POST', '/v1/checkout/sessions/cs_open/expire', checkoutSession('cs_open', 'expired'));

    $this->gateway->expire($order);

    expect($this->stripe->requestsTo('POST', '/v1/checkout/sessions/cs_open/expire'))->toHaveCount(1);
});

it('does nothing when the order has no session', function () {
    $this->gateway->expire(Order::factory()->create());

    expect($this->stripe->requests)->toBeEmpty();
});

it('ignores a session that can no longer be expired', function () {
    $order = Order::factory()->create(['stripe_session_id' => 'cs_done']);
    $this->stripe->respond('POST', '/v1/checkout/sessions/cs_done/expire', [
        'error' => ['type' => 'invalid_request_error', 'message' => 'Only Checkout Sessions with a status of open can be expired.'],
    ], 400);

    $this->gateway->expire($order);

    expect($this->stripe->requestsTo('POST', '/v1/checkout/sessions/cs_done/expire'))->toHaveCount(1);
});
