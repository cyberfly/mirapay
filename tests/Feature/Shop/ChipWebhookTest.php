<?php

use App\Enums\OrderStatus;
use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPaidMail;
use App\Mail\PaymentNeedsAttentionMail;
use App\Models\Order;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Http::preventStrayRequests();

    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $this->chipPrivateKey = $key;

    config([
        'services.payment_gateway' => 'chip',
        // Stored in .env with literal \n line breaks.
        'services.chip.public_key' => str_replace("\n", '\n', openssl_pkey_get_details($key)['key']),
    ]);
});

function postChip(array $purchase, ?string $signature = null)
{
    $payload = json_encode($purchase + ['event_type' => 'purchase.paid']);

    if ($signature === null) {
        openssl_sign($payload, $raw, test()->chipPrivateKey, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($raw);
    }

    return test()->call('POST', route('chip.webhook'), [], [], [], [
        'HTTP_X_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

function chipOrder(array $attributes = []): Order
{
    return Order::factory()->create($attributes + [
        'status' => OrderStatus::Pending,
        'payment_gateway' => 'chip',
        'payment_reference' => 'pur-1',
    ]);
}

it('marks the order paid and sends the paid emails', function () {
    Mail::fake();
    User::factory()->admin()->create();
    $this->mock(PaymentGateway::class)->shouldNotReceive('expire');
    $order = chipOrder();

    postChip(['id' => 'pur-1', 'status' => 'paid', 'reference' => $order->order_number])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_reference)->toBe('pur-1')
        ->and($order->paid_at)->not->toBeNull();
    Mail::assertQueued(OrderPaidMail::class, fn ($mail) => $mail->hasTo($order->email));
    Mail::assertQueued(NewOrderAdminMail::class);
});

it('rejects an invalid signature', function () {
    $order = chipOrder();

    postChip(['id' => 'pur-1', 'status' => 'paid'], base64_encode('forged'))->assertStatus(400);
    $this->call('POST', route('chip.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"id":"pur-1","status":"paid"}')
        ->assertStatus(400);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('rejects a payload signed for a different body', function () {
    $order = chipOrder();
    openssl_sign('{"id":"other"}', $raw, $this->chipPrivateKey, OPENSSL_ALGO_SHA256);

    postChip(['id' => 'pur-1', 'status' => 'paid'], base64_encode($raw))->assertStatus(400);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('rejects every callback when no public key is configured', function () {
    config(['services.chip.public_key' => null]);
    $order = chipOrder();

    postChip(['id' => 'pur-1', 'status' => 'paid'])->assertStatus(400);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('handles a duplicate callback once, without emailing again or raising an alert', function () {
    Mail::fake();
    Log::spy();
    User::factory()->admin()->create();
    $order = chipOrder();

    postChip(['id' => 'pur-1', 'status' => 'paid', 'reference' => $order->order_number])->assertOk();
    postChip(['id' => 'pur-1', 'status' => 'paid', 'reference' => $order->order_number])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
    Mail::assertQueuedCount(2); // customer + admin, once
    Mail::assertNotQueued(PaymentNeedsAttentionMail::class);
    Log::shouldNotHaveReceived('critical');
});

it('ignores callbacks for purchases that are not paid', function () {
    $order = chipOrder();

    postChip(['id' => 'pur-1', 'status' => 'cancelled'])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('attributes a payment on a superseded purchase to its order and cancels the current purchase', function () {
    Mail::fake();
    $this->mock(PaymentGateway::class)->shouldReceive('expire')->once()
        ->withArgs(fn (Order $o) => $o->payment_reference === 'pur-current');
    $order = chipOrder(['payment_reference' => 'pur-current']);

    postChip(['id' => 'pur-old', 'status' => 'paid', 'reference' => $order->order_number])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_reference)->toBe('pur-old');
});

it('does not pay a cancelled order and flags the payment for a refund', function () {
    Mail::fake();
    Log::spy();
    User::factory()->admin()->create(['email' => 'boss@example.com']);
    $order = chipOrder(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

    postChip(['id' => 'pur-1', 'status' => 'paid', 'reference' => $order->order_number])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
    Log::shouldHaveReceived('critical')->once();
    Mail::assertQueued(PaymentNeedsAttentionMail::class, fn ($mail) => $mail->hasTo('boss@example.com')
        && $mail->paymentReference === 'pur-1'
        && $mail->gateway === 'CHIP');
});

it('flags a payment for an order an admin already marked paid by hand', function () {
    Mail::fake();
    Log::spy();
    User::factory()->admin()->create();
    $order = Order::factory()->paid()->create(['payment_gateway' => 'chip', 'payment_reference' => null]);

    postChip(['id' => 'pur-9', 'status' => 'paid', 'reference' => $order->order_number])->assertOk();

    Log::shouldHaveReceived('critical')->once();
    Mail::assertQueued(PaymentNeedsAttentionMail::class);
});

it('logs but does not alert for a payment matching no order', function () {
    Mail::fake();
    Log::spy();

    postChip(['id' => 'pur-x', 'status' => 'paid', 'reference' => 'MP000000-NOPE'])->assertOk();

    Log::shouldHaveReceived('warning')->once();
    Mail::assertNothingQueued();
});
