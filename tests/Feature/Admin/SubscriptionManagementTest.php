<?php

use App\Enums\SubscriptionStatus;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(fn () => Mail::fake());

it('forbids non-admins from the subscription pages', function () {
    $subscription = Subscription::factory()->active()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.subscriptions.index'))->assertForbidden();
    $this->get(route('admin.subscriptions.show', $subscription))->assertForbidden();
});

it('lists and filters subscriptions', function () {
    $this->actingAs(User::factory()->admin()->create());
    $product = Product::factory()->subscription()->create();
    Subscription::factory()->forProduct($product)->active()->create(['customer_name' => 'Aminah Aktif']);
    Subscription::factory()->forProduct($product)->cancelled()->create(['customer_name' => 'Badrul Batal']);

    Livewire::test('pages::admin.subscriptions.index')
        ->assertSee('Aminah Aktif')
        ->assertSee('Badrul Batal')
        ->set('status', SubscriptionStatus::Active->value)
        ->assertSee('Aminah Aktif')
        ->assertDontSee('Badrul Batal');
});

it('shows a subscription and lets an admin cancel it', function () {
    $this->actingAs(User::factory()->admin()->create());
    $subscription = Subscription::factory()->active()->create();

    Livewire::test('pages::admin.subscriptions.show', ['subscription' => $subscription])
        ->assertSee($subscription->customer_name)
        ->call('cancel');

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled);
});

it('saves the billing interval on a product', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.products.create')
        ->set('form.name', 'Pelan Bulanan')
        ->set('form.slug', 'pelan-bulanan')
        ->set('form.price', '29')
        ->set('form.billing_interval', 'month')
        ->call('save')
        ->assertHasNoErrors();

    expect(Product::firstOrFail()->isSubscription())->toBeTrue();
});
