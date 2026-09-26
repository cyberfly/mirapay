<?php

use App\Enums\OrderStatus;
use App\Mail\PaymentNeedsAttentionMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Payments\ManualPaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\StripePaymentGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

it('attributes a payment made on a superseded session to its order and closes the current session', function () {
    $gateway = $this->mock(PaymentGateway::class);
    $gateway->shouldReceive('expire')->once()->withArgs(fn (Order $o) => $o->stripe_session_id === 'cs_current');

    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'stripe_session_id' => 'cs_current']);

    postStripe('checkout.session.completed', [
        'id' => 'cs_superseded', 'payment_status' => 'paid', 'payment_intent' => 'pi_old', 'client_reference_id' => $order->public_id,
    ])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_reference)->toBe('pi_old');
});

it('does not close the session that has just been paid', function () {
    $this->mock(PaymentGateway::class)->shouldNotReceive('expire');

    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'stripe_session_id' => 'cs_1']);

    postStripe('checkout.session.completed', [
        'id' => 'cs_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'client_reference_id' => $order->public_id,
    ])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('does not cancel an order when a superseded session expires', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'stripe_session_id' => 'cs_current']);

    postStripe('checkout.session.expired', ['id' => 'cs_superseded', 'client_reference_id' => $order->public_id])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('does not touch the gateway when cancelling an order for an expired session', function () {
    $this->mock(PaymentGateway::class)->shouldNotReceive('expire');

    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'stripe_session_id' => 'cs_2']);

    postStripe('checkout.session.expired', ['id' => 'cs_2'])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

it('flags a payment that arrives for a cancelled order', function () {
    Mail::fake();
    Log::spy();
    User::factory()->admin()->create(['email' => 'boss@example.com']);
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled, 'stripe_session_id' => 'cs_3']);

    postStripe('checkout.session.completed', ['id' => 'cs_3', 'payment_status' => 'paid', 'payment_intent' => 'pi_3'])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
    Log::shouldHaveReceived('critical')->once();
    Mail::assertQueued(PaymentNeedsAttentionMail::class, fn ($mail) => $mail->hasTo('boss@example.com') && $mail->paymentReference === 'pi_3');
});

it('flags a second payment for an order that is already paid', function () {
    Mail::fake();
    Log::spy();
    User::factory()->admin()->create();
    $order = Order::factory()->paid()->create(['payment_reference' => 'pi_first', 'stripe_session_id' => 'cs_4']);

    postStripe('checkout.session.completed', ['id' => 'cs_4', 'payment_status' => 'paid', 'payment_intent' => 'pi_second'])->assertOk();

    Log::shouldHaveReceived('critical')->once();
    Mail::assertQueued(PaymentNeedsAttentionMail::class);
});

it('flags a payment for an order an admin marked paid by hand', function () {
    Mail::fake();
    Log::spy();
    User::factory()->admin()->create();
    $order = Order::factory()->paid()->create(['payment_reference' => null, 'stripe_session_id' => 'cs_5']);

    postStripe('checkout.session.completed', ['id' => 'cs_5', 'payment_status' => 'paid', 'payment_intent' => 'pi_5'])->assertOk();

    Log::shouldHaveReceived('critical')->once();
    Mail::assertQueued(PaymentNeedsAttentionMail::class);
});

it('stays silent on redelivery of the payment that paid the order', function () {
    Mail::fake();
    Log::spy();
    User::factory()->admin()->create();
    Order::factory()->paid()->create(['payment_reference' => 'pi_6', 'stripe_session_id' => 'cs_6']);

    postStripe('checkout.session.completed', ['id' => 'cs_6', 'payment_status' => 'paid', 'payment_intent' => 'pi_6'])->assertOk();

    Log::shouldNotHaveReceived('critical');
    Mail::assertNotQueued(PaymentNeedsAttentionMail::class);
});

it('ignores a completed session that is not paid yet', function () {
    Mail::fake();
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'stripe_session_id' => 'cs_7']);

    postStripe('checkout.session.completed', ['id' => 'cs_7', 'payment_status' => 'unpaid', 'payment_intent' => null])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
    Mail::assertNotQueued(PaymentNeedsAttentionMail::class);
});

it('logs but does not alert for a payment matching no order', function () {
    Mail::fake();
    Log::spy();

    postStripe('checkout.session.completed', ['id' => 'cs_x', 'payment_status' => 'paid', 'payment_intent' => 'pi_x'])->assertOk();

    Log::shouldHaveReceived('warning')->once();
    Mail::assertNothingQueued();
});
