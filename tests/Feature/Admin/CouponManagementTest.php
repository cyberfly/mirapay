<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

it('creates a fixed coupon storing sen and uppercasing the code', function () {
    Livewire::test('pages::admin.coupons.create')
        ->set('form.code', ' jimat5 ')
        ->set('form.type', 'fixed')
        ->set('form.value', '5')
        ->call('save')
        ->assertHasNoErrors();

    $coupon = Coupon::firstOrFail();
    expect($coupon->code)->toBe('JIMAT5')->and($coupon->type)->toBe(CouponType::Fixed)->and($coupon->value)->toBe(500);
});

it('requires a unique code after normalisation', function () {
    Coupon::factory()->create(['code' => 'RAYA10']);

    Livewire::test('pages::admin.coupons.create')
        ->set('form.code', 'raya10')->set('form.value', '10')
        ->call('save')
        ->assertHasErrors(['form.code']);
});

it('limits percent values to 1-100', function () {
    Livewire::test('pages::admin.coupons.create')
        ->set('form.code', 'BIG')->set('form.value', '150')
        ->call('save')
        ->assertHasErrors(['form.value']);
});

it('requires expiry after start', function () {
    Livewire::test('pages::admin.coupons.create')
        ->set('form.code', 'DATES')->set('form.value', '10')
        ->set('form.starts_at', '2026-10-10T10:00')->set('form.expires_at', '2026-10-01T10:00')
        ->call('save')
        ->assertHasErrors(['form.expires_at']);
});

it('updates a coupon keeping its own code valid', function () {
    $coupon = Coupon::factory()->create(['code' => 'KEEP', 'value' => 10]);

    Livewire::test('pages::admin.coupons.edit', ['coupon' => $coupon])
        ->set('form.value', '20')
        ->call('save')
        ->assertHasNoErrors();

    expect($coupon->fresh()->value)->toBe(20);
});
