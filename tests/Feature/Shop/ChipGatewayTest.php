<?php

use App\Actions\Shop\CancelOrder;
use App\Actions\Shop\MarkOrderAsPaid;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Payments\ChipPaymentGateway;
use App\Payments\PaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const CHIP = 'https://gate.chip-in.asia/api/v1';

function chipPurchase(string $id, string $status = 'created'): array
{
    return ['id' => $id, 'status' => $status, 'checkout_url' => "https://gate.chip-in.asia/p/{$id}/"];
}

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'services.payment_gateway' => 'chip',
        'services.chip.brand_id' => 'brand-1',
        'services.chip.secret' => 'secret-1',
        'services.chip.currency' => 'MYR',
    ]);
});

it('is selected from config', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(ChipPaymentGateway::class);
});

it('checks out through a new CHIP purchase and remembers it on the order', function () {
    Http::fake([CHIP.'/purchases/' => Http::response(chipPurchase('pur-1'))]);
    $product = Product::factory()->digital()->create(['price' => 2500]);

    Livewire::test('pages::shop.product', ['product' => $product])
        ->set(['customer_name' => 'Ali', 'email' => 'ali@example.com', 'phone' => '0123456789'])
        ->call('placeOrder')
        ->assertRedirect('https://gate.chip-in.asia/p/pur-1/');

    $order = Order::sole();
    expect($order->payment_gateway)->toBe('chip')
        ->and($order->payment_reference)->toBe('pur-1')
        ->and($order->status)->toBe(OrderStatus::Pending);

    Http::assertSent(fn (Request $request) => $request->url() === CHIP.'/purchases/'
        && $request->hasHeader('Authorization', 'Bearer secret-1')
        && $request['brand_id'] === 'brand-1'
        && $request['reference'] === $order->order_number
        && $request['client']['email'] === 'ali@example.com'
        && $request['purchase']['currency'] === 'MYR'
        && $request['purchase']['products'][0]['price'] === $order->total
        && $request['success_redirect'] === route('shop.orders.show', $order)
        && $request['failure_redirect'] === route('shop.orders.show', $order)
        && $request['success_callback'] === route('chip.webhook'));
});

it('reuses a still-payable purchase instead of creating a second one', function () {
    Http::fake([CHIP.'/purchases/pur-open/' => Http::response(chipPurchase('pur-open', 'viewed'))]);
    $order = Order::factory()->create(['payment_gateway' => 'chip', 'payment_reference' => 'pur-open']);

    expect(app(ChipPaymentGateway::class)->checkoutUrl($order))->toBe('https://gate.chip-in.asia/p/pur-open/');
    Http::assertSentCount(1);
});

it('creates a fresh purchase once the previous one has expired', function () {
    Http::fake([
        CHIP.'/purchases/pur-old/' => Http::response(chipPurchase('pur-old', 'expired')),
        CHIP.'/purchases/' => Http::response(chipPurchase('pur-new')),
    ]);
    $order = Order::factory()->create(['payment_gateway' => 'chip', 'payment_reference' => 'pur-old']);

    expect(app(ChipPaymentGateway::class)->checkoutUrl($order))->toBe('https://gate.chip-in.asia/p/pur-new/')
        ->and($order->fresh()->payment_reference)->toBe('pur-new');
});

it('does not offer a second payment when the purchase is already paid', function () {
    Http::fake([CHIP.'/purchases/pur-paid/' => Http::response(chipPurchase('pur-paid', 'paid'))]);
    $order = Order::factory()->create(['payment_gateway' => 'chip', 'payment_reference' => 'pur-paid']);

    expect(app(ChipPaymentGateway::class)->checkoutUrl($order))->toBe(route('shop.orders.show', $order));
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('cancels the purchase when a pending order is cancelled, so it can no longer be paid', function () {
    Http::fake([CHIP.'/purchases/pur-1/cancel/' => Http::response(chipPurchase('pur-1', 'cancelled'))]);
    $order = Order::factory()->create(['payment_gateway' => 'chip', 'payment_reference' => 'pur-1']);

    expect(app(CancelOrder::class)($order)->status)->toBe(OrderStatus::Cancelled);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === CHIP.'/purchases/pur-1/cancel/');
});

it('cancels the purchase when an admin marks the order paid by hand', function () {
    Http::fake([CHIP.'/purchases/pur-1/cancel/' => Http::response(chipPurchase('pur-1', 'cancelled'))]);
    $order = Order::factory()->create(['payment_gateway' => 'chip', 'payment_reference' => 'pur-1']);

    expect(app(MarkOrderAsPaid::class)($order)->status)->toBe(OrderStatus::Paid);
    Http::assertSent(fn (Request $request) => $request->url() === CHIP.'/purchases/pur-1/cancel/');
});

it('treats a purchase that can no longer be cancelled as closed', function () {
    Http::fake([CHIP.'/purchases/pur-1/cancel/' => Http::response(['__all__' => ['Purchase is paid']], 400)]);
    $order = Order::factory()->create(['payment_gateway' => 'chip', 'payment_reference' => 'pur-1']);

    app(ChipPaymentGateway::class)->expire($order);

    Http::assertSentCount(1);
});

it('leaves orders from other gateways alone', function () {
    $order = Order::factory()->create(['payment_gateway' => 'stripe', 'stripe_session_id' => 'cs_1']);

    app(ChipPaymentGateway::class)->expire($order);

    Http::assertNothingSent();
});

it('offers to pay again on the order page only through the gateway the order was placed with', function () {
    $order = Order::factory()->create(['payment_gateway' => 'chip', 'payment_reference' => 'pur-1']);
    $stripeOrder = Order::factory()->create(['payment_gateway' => 'stripe', 'stripe_session_id' => 'cs_1']);

    Livewire::test('pages::shop.order', ['order' => $order])->assertSee('Bayar sekarang');
    Livewire::test('pages::shop.order', ['order' => $stripeOrder])
        ->assertSee('Menunggu pengesahan bayaran')
        ->assertDontSee('Bayar sekarang')
        ->call('pay')
        ->assertNoRedirect();
});
