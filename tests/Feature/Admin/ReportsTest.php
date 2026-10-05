<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Reports\Reports;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-15 12:00:00');
});

function sale(array $attributes = [], string $state = 'paid'): Order
{
    $factory = Order::factory();

    return ($state === 'pending' ? $factory : $factory->{$state}())->create($attributes);
}

function reportRoutes(): array
{
    return [
        'sales' => [fn () => route('admin.reports.sales')],
        'products' => [fn () => route('admin.reports.products')],
        'coupons' => [fn () => route('admin.reports.coupons')],
        'customers' => [fn () => route('admin.reports.customers')],
        'statuses' => [fn () => route('admin.reports.statuses')],
        'export' => [fn () => route('admin.reports.export', 'sales')],
    ];
}

it('redirects guests and forbids non-admins from reports', function (Closure $url) {
    $this->get($url())->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($url())->assertForbidden();
})->with(reportRoutes());

it('lets admins open every report', function (Closure $url) {
    $this->actingAs(User::factory()->admin()->create())->get($url())->assertOk();
})->with(reportRoutes());

it('shows the reports menu only to admins', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertDontSee('Laporan');
    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertSee('Laporan');
});

it('totals only paid and shipped orders by paid date', function () {
    sale(['total' => 10000, 'paid_at' => '2026-10-10 09:00:00']);
    sale(['total' => 5000, 'paid_at' => '2026-10-10 23:59:59'], 'shipped');
    sale(['total' => 99999], 'cancelled');
    sale(['total' => 99999], 'pending');

    $summary = Reports::make('2026-10-10', '2026-10-10')->salesSummary();

    expect($summary)->toBe(['revenue' => 15000, 'orders' => 2, 'average' => 7500]);
});

it('filters by the date range inclusively', function () {
    sale(['total' => 100, 'paid_at' => '2026-10-01 00:00:00']);
    sale(['total' => 200, 'paid_at' => '2026-10-05 23:59:59']);
    sale(['total' => 400, 'paid_at' => '2026-09-30 23:59:59']);
    sale(['total' => 800, 'paid_at' => '2026-10-06 00:00:00']);

    expect(Reports::make('2026-10-01', '2026-10-05')->salesSummary()['revenue'])->toBe(300);
});

it('defaults to the last 30 days and swaps a reversed range', function () {
    $default = Reports::make()->range;
    expect($default->from->toDateString())->toBe('2026-09-16')->and($default->to->toDateString())->toBe('2026-10-15');

    $swapped = Reports::make('2026-10-10', '2026-10-01')->range;
    expect($swapped->from->toDateString())->toBe('2026-10-01')->and($swapped->to->toDateString())->toBe('2026-10-10');

    expect(Reports::make('garbage', 'nope')->range->to->toDateString())->toBe('2026-10-15');
});

it('groups sales by day, week and month with empty periods zero-filled', function () {
    sale(['total' => 1000, 'paid_at' => '2026-10-05 10:00:00']); // Monday
    sale(['total' => 3000, 'paid_at' => '2026-10-07 10:00:00']); // same week
    sale(['total' => 500, 'paid_at' => '2026-09-30 10:00:00']);

    $reports = Reports::make('2026-09-28', '2026-10-11');

    expect($reports->salesByPeriod('day'))->toHaveCount(14);

    $weeks = $reports->salesByPeriod('week');
    expect($weeks->pluck('revenue')->all())->toBe([500, 4000])
        ->and($weeks->last()['orders'])->toBe(2)
        ->and($weeks->last()['average'])->toBe(2000);

    $months = $reports->salesByPeriod('month');
    expect($months->pluck('revenue')->all())->toBe([500, 4000]);
});

it('matches the series total with the summary', function () {
    sale(['total' => 2550, 'paid_at' => '2026-10-14 10:00:00']);
    sale(['total' => 1000, 'paid_at' => '2026-10-15 10:00:00']);

    $reports = Reports::make('2026-10-01', '2026-10-15');

    expect($reports->salesByPeriod()->sum('revenue'))->toBe(3550)
        ->and($reports->salesSummary()['revenue'])->toBe(3550);
});

it('ranks products and categories by quantity then revenue', function () {
    $books = Category::factory()->create(['name' => 'Buku']);
    $tools = Category::factory()->create(['name' => 'Alat']);
    $a = Product::factory()->create(['name' => 'Buku A', 'category_id' => $books->id]);
    $b = Product::factory()->create(['name' => 'Alat B', 'category_id' => $tools->id]);

    sale(['product_id' => $a->id, 'product_name' => 'Buku A', 'quantity' => 1, 'total' => 5000]);
    sale(['product_id' => $b->id, 'product_name' => 'Alat B', 'quantity' => 3, 'total' => 3000]);
    sale(['product_id' => $b->id, 'product_name' => 'Alat B', 'quantity' => 2, 'total' => 2000]);
    sale(['product_id' => $a->id, 'product_name' => 'Buku A', 'quantity' => 99, 'total' => 99999], 'cancelled');

    $reports = Reports::make('2026-10-01', '2026-10-31');

    expect($reports->products()->first())->toMatchArray(['name' => 'Alat B', 'category' => 'Alat', 'units' => 5, 'revenue' => 5000])
        ->and($reports->categories()->pluck('category')->all())->toBe(['Alat', 'Buku']);
});

it('reports coupon usage and discount given', function () {
    $save10 = Coupon::factory()->create(['code' => 'SAVE10']);
    $vip = Coupon::factory()->create(['code' => 'VIP']);

    sale(['coupon_id' => $save10->id, 'coupon_code' => 'SAVE10', 'discount' => 500, 'total' => 4500]);
    sale(['coupon_id' => $save10->id, 'coupon_code' => 'SAVE10', 'discount' => 300, 'total' => 2700], 'shipped');
    sale(['coupon_id' => $vip->id, 'coupon_code' => 'VIP', 'discount' => 1000, 'total' => 9000]);
    sale(['coupon_id' => $vip->id, 'coupon_code' => 'VIP', 'discount' => 1000], 'cancelled');
    sale(['total' => 1000]);

    $rows = Reports::make('2026-10-01', '2026-10-31')->coupons();

    expect($rows)->toHaveCount(2)
        ->and($rows->first())->toBe(['code' => 'SAVE10', 'uses' => 2, 'discount' => 800, 'revenue' => 7200])
        ->and($rows->last())->toBe(['code' => 'VIP', 'uses' => 1, 'discount' => 1000, 'revenue' => 9000]);
});

it('reports new customers, top spenders and repeat rate', function () {
    sale(['email' => 'old@example.com', 'total' => 1000, 'paid_at' => '2026-08-01 10:00:00']);
    sale(['email' => 'Old@Example.com', 'total' => 2000, 'paid_at' => '2026-10-02 10:00:00']);
    sale(['email' => 'new@example.com', 'customer_name' => 'Baru', 'total' => 9000, 'paid_at' => '2026-10-03 10:00:00']);
    sale(['email' => 'new@example.com', 'customer_name' => 'Baru', 'total' => 1000, 'paid_at' => '2026-10-04 10:00:00']);
    sale(['email' => 'once@example.com', 'total' => 500, 'paid_at' => '2026-10-05 10:00:00']);
    sale(['email' => 'ghost@example.com', 'total' => 500], 'pending');

    $reports = Reports::make('2026-10-01', '2026-10-31');

    expect($reports->customerSummary())->toBe(['new' => 2, 'active' => 3, 'repeat' => 1, 'repeat_rate' => 33.3])
        ->and($reports->topCustomers()->first())->toBe(['email' => 'new@example.com', 'name' => 'Baru', 'orders' => 2, 'spend' => 10000]);
});

it('breaks orders down by status', function () {
    sale([], 'pending');
    sale([], 'pending');
    sale(['total' => 700]);
    sale([], 'cancelled');
    Order::factory()->paid()->create(['created_at' => '2026-08-01 10:00:00']);

    $rows = Reports::make('2026-10-01', '2026-10-31')->statuses()->keyBy(fn ($r) => $r['status']->value);

    expect($rows['pending']['orders'])->toBe(2)
        ->and($rows['paid']['orders'])->toBe(1)
        ->and($rows['paid']['value'])->toBe(700)
        ->and($rows['shipped']['orders'])->toBe(0)
        ->and($rows['cancelled']['orders'])->toBe(1);
});

it('renders the sales page for a chosen range and interval', function () {
    $this->actingAs(User::factory()->admin()->create());
    sale(['total' => 12345, 'paid_at' => '2026-10-10 10:00:00']);
    sale(['total' => 77700, 'paid_at' => '2026-09-01 10:00:00']);

    Livewire::withQueryParams(['from' => '2026-10-01', 'to' => '2026-10-15'])
        ->test('pages::admin.reports.sales')
        ->assertSee('RM123.45')
        ->assertDontSee('RM777.00')
        ->assertSee('salesChart', false)
        ->set('interval', 'week')
        ->assertSet('interval', 'week')
        ->set('interval', 'bogus')
        ->assertSet('interval', 'day');
});

it('rejects non-admins on the report components', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::admin.reports.sales')->assertForbidden();
});

it('exports each report as csv', function (string $report, string $needle) {
    $this->actingAs(User::factory()->admin()->create());
    sale([
        'product_name' => 'Kopi', 'quantity' => 2, 'total' => 12345, 'discount' => 500, 'coupon_code' => 'SAVE10',
        'customer_name' => 'Ali', 'email' => 'ali@example.com', 'paid_at' => '2026-10-10 10:00:00',
    ]);

    $response = $this->get(route('admin.reports.export', ['report' => $report, 'from' => '2026-10-01', 'to' => '2026-10-31']));

    $response->assertOk()->assertDownload("laporan-{$report}-2026-10-01-2026-10-31.csv");
    expect($response->streamedContent())->toContain($needle);
})->with([
    'sales' => ['sales', '"10 Oct 2026",1,123.45,123.45'],
    'products' => ['products', 'Kopi'],
    'coupons' => ['coupons', 'SAVE10,1,5.00'],
    'customers' => ['customers', 'ali@example.com,Ali,1,123.45'],
    'statuses' => ['statuses', 'Dibayar,1,123.45'],
]);

it('forbids csv export for non-admins and 404s unknown reports', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.reports.export', 'sales'))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.export', 'nope'))->assertNotFound();
});

it('neutralises spreadsheet formulas in csv cells', function () {
    $this->actingAs(User::factory()->admin()->create());
    sale(['customer_name' => '=HYPERLINK("x")', 'email' => 'evil@example.com', 'paid_at' => '2026-10-10 10:00:00']);

    $csv = $this->get(route('admin.reports.export', ['report' => 'customers', 'from' => '2026-10-01', 'to' => '2026-10-31']))->streamedContent();

    expect($csv)->toContain("\"'=HYPERLINK")->not->toContain(',=HYPERLINK');
});

it('matches the sales summary with the orders list figures', function () {
    sale(['total' => 4000, 'paid_at' => '2026-10-10 10:00:00']);
    sale(['total' => 6000, 'paid_at' => '2026-10-11 10:00:00'], 'shipped');

    $listed = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Shipped])
        ->whereBetween('paid_at', ['2026-10-01 00:00:00', '2026-10-31 23:59:59'])->get();

    expect(Reports::make('2026-10-01', '2026-10-31')->salesSummary())
        ->toMatchArray(['revenue' => $listed->sum('total'), 'orders' => $listed->count()]);
});
