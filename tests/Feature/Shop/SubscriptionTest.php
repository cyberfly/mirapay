<?php

use App\Actions\Shop\CancelOrder;
use App\Actions\Shop\MarkOrderAsPaid;
use App\Actions\Subscriptions\CancelSubscription;
use App\Actions\Subscriptions\PauseSubscription;
use App\Actions\Subscriptions\ResumeSubscription;
use App\Enums\BillingInterval;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionCancelledMail;
use App\Mail\SubscriptionPaymentFailedMail;
use App\Mail\SubscriptionRenewalDueMail;
use App\Mail\SubscriptionRenewedMail;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('place-order:127.0.0.1');
});

function startSubscription(Product $product, array $extra = []): Order
{
    Livewire::test('pages::shop.product', ['product' => $product])
        ->set(['customer_name' => 'Ali', 'email' => 'ali@example.com', 'phone' => '0123456789'] + $extra)
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertRedirect();

    return Order::latest('id')->firstOrFail();
}

function dueSubscription(array $state = []): Subscription
{
    $product = Product::factory()->subscription()->create(['price' => 2900]);

    return Subscription::factory()->forProduct($product)->active(0)->create($state);
}

// --- Subscribe ---------------------------------------------------------------------------

it('creates a pending subscription and its first order at checkout', function () {
    $product = Product::factory()->subscription()->create(['price' => 2900]);

    $order = startSubscription($product, ['quantity' => 2]);

    $subscription = Subscription::firstOrFail();
    expect($subscription->status)->toBe(SubscriptionStatus::Pending)
        ->and($subscription->unit_price)->toBe(2900)
        ->and($subscription->quantity)->toBe(2)
        ->and($subscription->email)->toBe('ali@example.com')
        ->and($order->subscription_id)->toBe($subscription->id)
        ->and($order->is_renewal)->toBeFalse()
        ->and($order->total)->toBe(5800)
        ->and($subscription->hasAccess())->toBeFalse();
});

it('does not create a subscription for a one-off product', function () {
    startSubscription(Product::factory()->create());

    expect(Subscription::count())->toBe(0);
});

it('activates the subscription when the first order is paid', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $order = startSubscription(Product::factory()->subscription()->create());

    app(MarkOrderAsPaid::class)($order);

    $subscription = Subscription::firstOrFail();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_end->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString())
        ->and($subscription->next_billing_date->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString())
        ->and($subscription->hasAccess())->toBeTrue();
});

it('uses a yearly period for a yearly plan', function () {
    $order = startSubscription(Product::factory()->subscription(BillingInterval::Year)->create());

    app(MarkOrderAsPaid::class)($order);

    expect(Subscription::firstOrFail()->current_period_end->toDateString())->toBe(now()->addYearNoOverflow()->toDateString());
});

it('cancels the subscription when its unpaid first order is cancelled', function () {
    $order = startSubscription(Product::factory()->subscription()->create());

    app(CancelOrder::class)($order);

    expect(Subscription::firstOrFail()->status)->toBe(SubscriptionStatus::Cancelled);
});

// --- Renew -------------------------------------------------------------------------------

it('raises a renewal order for a due subscription and emails a payment link', function () {
    $subscription = dueSubscription();

    $this->artisan('subscriptions:renew')->assertSuccessful();

    $order = Order::firstOrFail();
    expect($order->is_renewal)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total)->toBe(2900)
        ->and($order->subscription_id)->toBe($subscription->id)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue);
    Mail::assertQueued(SubscriptionRenewalDueMail::class, fn ($mail) => $mail->hasTo($subscription->email));
});

it('renews on the due date and produces a paid order', function () {
    $subscription = dueSubscription();
    $due = $subscription->next_billing_date;

    $this->artisan('subscriptions:renew');
    app(MarkOrderAsPaid::class)(Order::firstOrFail());

    $subscription->refresh();
    expect(Order::firstOrFail()->status)->toBe(OrderStatus::Paid)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->past_due_since)->toBeNull()
        ->and($subscription->current_period_start->toDateString())->toBe($due->toDateString())
        ->and($subscription->next_billing_date->toDateString())->toBe($due->addMonthNoOverflow()->toDateString());
    Mail::assertQueued(SubscriptionRenewedMail::class);
});

it('does not renew a subscription that is not due yet', function () {
    Subscription::factory()->forProduct(Product::factory()->subscription()->create())->active(10)->create();

    $this->artisan('subscriptions:renew');

    expect(Order::count())->toBe(0);
});

it('never bills the same period twice', function () {
    dueSubscription();

    $this->artisan('subscriptions:renew');
    $this->artisan('subscriptions:renew');

    expect(Order::count())->toBe(1);
});

it('does not renew paused, cancelled or pending subscriptions', function () {
    $product = Product::factory()->subscription()->create();
    Subscription::factory()->forProduct($product)->paused()->create(['next_billing_date' => today()]);
    Subscription::factory()->forProduct($product)->cancelled()->create(['next_billing_date' => today()]);
    Subscription::factory()->forProduct($product)->create(['next_billing_date' => today()]);

    $this->artisan('subscriptions:renew');

    expect(Order::count())->toBe(0);
});

it('bills renewals at the regular price without the first-order coupon', function () {
    Coupon::factory()->percent(50)->create(['code' => 'HALF']);
    $product = Product::factory()->subscription()->create(['price' => 2000]);

    $order = startSubscription($product, ['quantity' => 1]);
    expect($order->total)->toBe(2000);

    Livewire::test('pages::shop.product', ['product' => $product])
        ->set(['customer_name' => 'Ali', 'email' => 'b@example.com', 'phone' => '0123456789', 'couponCode' => 'half'])
        ->call('applyCoupon')
        ->call('placeOrder');
    $first = Order::latest('id')->firstOrFail();
    expect($first->total)->toBe(1000);

    app(MarkOrderAsPaid::class)($first);
    $first->subscription->update(['next_billing_date' => today()]);

    $this->artisan('subscriptions:renew');

    expect(Order::where('is_renewal', true)->firstOrFail()->total)->toBe(2000);
});

it('keeps the shipping fee and address on physical renewals', function () {
    $product = Product::factory()->subscription()->physical(800)->create(['price' => 1000]);
    $subscription = Subscription::factory()->forProduct($product)->active(0)->create([
        'address_line1' => '1 Jalan Ampang', 'postcode' => '50450', 'city' => 'KL', 'state' => 'kuala-lumpur',
    ]);

    $this->artisan('subscriptions:renew');

    $order = Order::firstOrFail();
    expect($order->total)->toBe(1800)
        ->and($order->requires_shipping)->toBeTrue()
        ->and($order->address_line1)->toBe('1 Jalan Ampang');
});

// --- Failed renewals ---------------------------------------------------------------------

it('reminds the customer while a renewal stays unpaid', function () {
    $subscription = dueSubscription();
    $this->artisan('subscriptions:renew');
    Mail::assertNotQueued(SubscriptionPaymentFailedMail::class);

    $this->travel(4)->days();
    $this->artisan('subscriptions:renew');

    Mail::assertQueued(SubscriptionPaymentFailedMail::class, 1);
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->fresh()->hasAccess())->toBeTrue();
});

it('expires the subscription and cancels the renewal after the grace period', function () {
    $subscription = dueSubscription();
    $this->artisan('subscriptions:renew');

    $this->travel(config('subscriptions.grace_days') + 1)->days();
    $this->artisan('subscriptions:renew');

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($subscription->fresh()->hasAccess())->toBeFalse()
        ->and(Order::firstOrFail()->status)->toBe(OrderStatus::Cancelled);
    Mail::assertQueued(SubscriptionCancelledMail::class, fn ($mail) => $mail->expired);
});

it('lets a late payment inside the grace period restore the subscription', function () {
    $subscription = dueSubscription();
    $this->artisan('subscriptions:renew');

    $this->travel(3)->days();
    app(MarkOrderAsPaid::class)(Order::firstOrFail());

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('does not touch stock or coupons when a renewal is cancelled', function () {
    $product = Product::factory()->subscription()->withStock(5)->create();
    $subscription = Subscription::factory()->forProduct($product)->active(0)->create();
    $this->artisan('subscriptions:renew');

    app(CancelOrder::class)(Order::firstOrFail());

    expect($product->fresh()->stock)->toBe(5);
});

// --- Cancel / pause / resume -------------------------------------------------------------

it('stops renewals but keeps access until the period ends when cancelled', function () {
    $subscription = Subscription::factory()->forProduct(Product::factory()->subscription()->create())->active(10)->create();

    app(CancelSubscription::class)($subscription);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->hasAccess())->toBeTrue()
        ->and($subscription->ends_at->toDateString())->toBe($subscription->current_period_end->toDateString());
    Mail::assertQueued(SubscriptionCancelledMail::class, fn ($mail) => ! $mail->expired);

    $this->travel(11)->days();
    $this->artisan('subscriptions:renew');

    expect($subscription->fresh()->hasAccess())->toBeFalse()
        ->and(Order::count())->toBe(0);
});

it('cancels the open renewal order when a past due subscription is cancelled', function () {
    $subscription = dueSubscription();
    $this->artisan('subscriptions:renew');

    app(CancelSubscription::class)($subscription->fresh());

    expect(Order::firstOrFail()->status)->toBe(OrderStatus::Cancelled);
});

it('refuses to cancel a subscription twice', function () {
    $subscription = Subscription::factory()->forProduct(Product::factory()->subscription()->create())->cancelled()->create();

    app(CancelSubscription::class)($subscription);
})->throws(DomainException::class);

it('pauses and resumes a subscription', function () {
    $subscription = Subscription::factory()->forProduct(Product::factory()->subscription()->create())->active(10)->create();

    app(PauseSubscription::class)($subscription);
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Paused);

    $this->artisan('subscriptions:renew');
    expect(Order::count())->toBe(0);

    app(ResumeSubscription::class)($subscription->fresh());
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('bills straight away when resumed after the paid period lapsed', function () {
    $subscription = Subscription::factory()->forProduct(Product::factory()->subscription()->create())->paused()->create();
    $this->travel(40)->days();

    app(ResumeSubscription::class)($subscription);
    $this->artisan('subscriptions:renew');

    expect(Order::where('is_renewal', true)->count())->toBe(1);
});

// --- Customer page -----------------------------------------------------------------------

it('lets the customer cancel from the subscription page', function () {
    $subscription = Subscription::factory()->forProduct(Product::factory()->subscription()->create())->active()->create();

    Livewire::test('pages::shop.subscription', ['subscription' => $subscription])
        ->assertSee($subscription->product_name)
        ->call('cancel');

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled);
});

it('lets the customer pause and resume from the subscription page', function () {
    $subscription = Subscription::factory()->forProduct(Product::factory()->subscription()->create())->active()->create();

    Livewire::test('pages::shop.subscription', ['subscription' => $subscription])
        ->call('pause')
        ->call('resume');

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});
