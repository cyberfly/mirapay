<?php

use App\Enums\OrderStatus;
use App\Mail\OrderPaidMail;
use App\Mail\PaymentNeedsAttentionMail;
use App\Models\Order;
use App\Models\User;
use App\Payments\ChipPaymentGateway;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $this->privateKey);

    config([
        'services.payment_gateway' => 'chip',
        'services.chip.public_key' => openssl_pkey_get_details($key)['key'],
        'services.chip.brand_id' => 'brand-1',
        'services.chip.api_key' => 'secret',
    ]);
});

function postChip(array $purchase, ?string $signature = null)
{
    $payload = json_encode($purchase);

    if ($signature === null) {
        openssl_sign($payload, $raw, test()->privateKey, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($raw);
    }

    return test()->call('POST', route('chip.webhook'), [], [], [], [
        'HTTP_X_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

function chipPaid(string $id, ?Order $order = null): array
{
    return ['event_type' => 'purchase.paid', 'id' => $id, 'status' => 'paid', 'reference' => $order?->public_id];
}

function chipOrder(array $attributes = []): Order
{
    return Order::factory()->create($attributes + ['payment_gateway' => 'chip', 'payment_reference' => 'pur_1']);
}

it('selects the chip gateway from config', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(ChipPaymentGateway::class);
});

it('creates a purchase for the order and remembers its id', function () {
    Http::fake(['*/purchases/' => Http::response(['id' => 'pur_new', 'checkout_url' => 'https://chip.test/pay/pur_new', 'status' => 'created'])]);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    expect(app(PaymentGateway::class)->checkoutUrl($order))->toBe('https://chip.test/pay/pur_new')
        ->and($order->fresh()->payment_reference)->toBe('pur_new')
        ->and($order->fresh()->payment_gateway)->toBe('chip');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret')
        && $request['brand_id'] === 'brand-1'
        && $request['reference'] === $order->public_id
        && $request['purchase']['products'][0]['price'] === $order->total
        && $request['success_redirect'] === route('shop.orders.show', $order)
        && $request['success_callback'] === route('chip.webhook'));
});

it('reuses the still-payable purchase instead of creating a second one', function () {
    Http::fake(['*/purchases/pur_1/' => Http::response(['id' => 'pur_1', 'status' => 'created', 'checkout_url' => 'https://chip.test/pay/pur_1'])]);
    $order = chipOrder();

    expect(app(PaymentGateway::class)->checkoutUrl($order))->toBe('https://chip.test/pay/pur_1');
    Http::assertSentCount(1);
});

it('cancels the purchase when the order is expired', function () {
    Http::fake(['*/purchases/pur_1/cancel/' => Http::response(['id' => 'pur_1', 'status' => 'cancelled'])]);

    app(PaymentGateway::class)->expire(chipOrder());

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/purchases/pur_1/cancel/'));
});

it('does not fail when the purchase can no longer be cancelled', function () {
    Http::fake(['*' => Http::response(['detail' => 'nope'], 400)]);

    app(PaymentGateway::class)->expire(chipOrder());

    expect(true)->toBeTrue();
});

it('marks the order paid and sends the paid emails', function () {
    Mail::fake();
    $order = chipOrder(['status' => OrderStatus::Pending]);

    postChip(chipPaid('pur_1', $order))->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_reference)->toBe('pur_1');
    Mail::assertQueued(OrderPaidMail::class);
});

it('rejects an invalid signature', function () {
    $order = chipOrder(['status' => OrderStatus::Pending]);

    postChip(chipPaid('pur_1', $order), base64_encode('bad'))->assertStatus(400);
    postChip(chipPaid('pur_1', $order), '')->assertStatus(400);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('rejects a payload that was tampered with after signing', function () {
    $order = chipOrder(['status' => OrderStatus::Pending]);
    openssl_sign(json_encode(chipPaid('pur_other')), $raw, $this->privateKey, OPENSSL_ALGO_SHA256);

    postChip(chipPaid('pur_1', $order), base64_encode($raw))->assertStatus(400);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('ignores a duplicate webhook without double counting', function () {
    Mail::fake();
    Log::spy();
    $order = chipOrder(['status' => OrderStatus::Pending]);

    postChip(chipPaid('pur_1', $order))->assertOk();
    postChip(chipPaid('pur_1', $order))->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
    Mail::assertQueued(OrderPaidMail::class, 1);
    Mail::assertNotQueued(PaymentNeedsAttentionMail::class);
    Log::shouldNotHaveReceived('critical');
});

it('flags a payment that arrives for a cancelled order', function () {
    Mail::fake();
    Log::spy();
    User::factory()->admin()->create(['email' => 'boss@example.com']);
    $order = chipOrder(['status' => OrderStatus::Cancelled]);

    postChip(chipPaid('pur_1', $order))->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
    Log::shouldHaveReceived('critical')->once();
    Mail::assertQueued(PaymentNeedsAttentionMail::class, fn ($mail) => $mail->hasTo('boss@example.com') && $mail->paymentReference === 'pur_1');
});

it('attributes a payment on a superseded purchase and closes the current one', function () {
    Http::fake(['*/purchases/pur_current/cancel/' => Http::response(['status' => 'cancelled'])]);
    $order = chipOrder(['status' => OrderStatus::Pending, 'payment_reference' => 'pur_current']);

    postChip(chipPaid('pur_old', $order))->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_reference)->toBe('pur_old');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/purchases/pur_current/cancel/'));
});

it('flags a second payment for an order that is already paid', function () {
    Mail::fake();
    Log::spy();
    User::factory()->admin()->create();
    $order = Order::factory()->paid()->create(['payment_gateway' => 'chip', 'payment_reference' => 'pur_first']);

    postChip(chipPaid('pur_second', $order))->assertOk();

    Log::shouldHaveReceived('critical')->once();
    Mail::assertQueued(PaymentNeedsAttentionMail::class);
});

it('cancels the pending order when its purchase expires', function () {
    $order = chipOrder(['status' => OrderStatus::Pending]);

    postChip(['event_type' => 'purchase.expired', 'id' => 'pur_1'])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

it('does not cancel an order when a superseded purchase expires', function () {
    $order = chipOrder(['status' => OrderStatus::Pending, 'payment_reference' => 'pur_current']);

    postChip(['event_type' => 'purchase.expired', 'id' => 'pur_old', 'reference' => $order->public_id])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});
