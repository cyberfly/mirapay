<?php

use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

it('counts only paid and shipped orders in the range as sales', function () {
    $this->actingAs(User::factory()->admin()->create());

    Order::factory()->paid()->create(['total' => 10000, 'paid_at' => now()]);
    Order::factory()->shipped()->create(['total' => 5000, 'paid_at' => now()->subDays(2)]);
    Order::factory()->create(['total' => 99999]);
    Order::factory()->cancelled()->create(['total' => 99999]);
    Order::factory()->paid()->create(['total' => 77700, 'paid_at' => now()->subDays(60)]);

    $component = Livewire::test('pages::dashboard');
    $summary = $component->instance()->summary;

    expect($summary)->toMatchArray(['revenue' => 15000, 'orders' => 2, 'average' => 7500, 'pending' => 1]);

    $component->set('days', 90);
    expect($component->instance()->summary['revenue'])->toBe(92700);
});

it('builds a zero-filled daily series', function () {
    $this->actingAs(User::factory()->admin()->create());
    Order::factory()->paid()->create(['total' => 2550, 'paid_at' => now()]);

    $series = Livewire::test('pages::dashboard')->instance()->series;

    expect($series['labels'])->toHaveCount(30)
        ->and($series['values'])->toHaveCount(30)
        ->and(end($series['values']))->toBe(25.5)
        ->and(array_sum($series['values']))->toBe(25.5);
});

it('ranks top products by units sold', function () {
    $this->actingAs(User::factory()->admin()->create());
    Order::factory()->paid()->create(['product_name' => 'Kecil', 'quantity' => 1, 'total' => 100]);
    Order::factory()->paid()->create(['product_name' => 'Besar', 'quantity' => 5, 'total' => 500]);

    $top = Livewire::test('pages::dashboard')->instance()->topProducts;

    expect($top->first()->product_name)->toBe('Besar');
});

it('falls back to 30 days for an invalid range', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::dashboard')->set('days', 5)->assertSet('days', 30);
});

it('hides shop stats from non-admins', function () {
    $this->actingAs(User::factory()->create());
    Order::factory()->paid()->create(['total' => 12345]);

    $this->get(route('dashboard'))->assertOk()->assertDontSee('Jumlah jualan')->assertDontSee('123.45');
});

it('shows the sales chart to admins', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('dashboard'))->assertOk()->assertSee('Graf jualan harian')->assertSee('salesChart', false);
});
