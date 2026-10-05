<?php

namespace App\Support\Reports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only sales reporting. Revenue is the order total (after discount, including shipping),
 * recognised on paid_at, and only counts Paid/Shipped orders. All amounts are in sen.
 */
class Reports
{
    public const INTERVALS = ['day', 'week', 'month'];

    /** @var Collection<int, array{product_id: int, name: string, category: string, units: int, revenue: int}>|null */
    private ?Collection $products = null;

    public function __construct(public readonly ReportRange $range) {}

    public static function make(?string $from = null, ?string $to = null): self
    {
        return new self(new ReportRange($from, $to));
    }

    /**
     * @return Builder<Order>
     */
    private function sales(): Builder
    {
        return Order::query()->sales()->paidBetween($this->range->from, $this->range->to);
    }

    /**
     * @return array{revenue: int, orders: int, average: int}
     */
    public function salesSummary(): array
    {
        $row = $this->sales()->selectRaw('count(*) as orders, coalesce(sum(total), 0) as revenue')->first();
        $orders = (int) $row?->getAttribute('orders');
        $revenue = (int) $row?->getAttribute('revenue');

        return ['revenue' => $revenue, 'orders' => $orders, 'average' => $orders > 0 ? intdiv($revenue, $orders) : 0];
    }

    /**
     * Zero-filled rows per day, week (starting Monday) or month.
     *
     * @return Collection<int, array{period: string, label: string, orders: int, revenue: int, average: int}>
     */
    public function salesByPeriod(string $interval = 'day'): Collection
    {
        $interval = in_array($interval, self::INTERVALS, true) ? $interval : 'day';

        $daily = $this->sales()
            ->selectRaw('date(paid_at) as day, count(*) as orders, sum(total) as revenue')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $buckets = [];

        for ($date = $this->range->from->startOfDay(); $date->lte($this->range->to); $date = $date->addDay()) {
            $key = match ($interval) {
                'week' => $date->startOfWeek()->toDateString(),
                'month' => $date->format('Y-m'),
                default => $date->toDateString(),
            };

            $buckets[$key] ??= ['period' => $key, 'label' => $this->periodLabel($interval, $date), 'orders' => 0, 'revenue' => 0];

            if ($row = $daily->get($date->toDateString())) {
                $buckets[$key]['orders'] += (int) $row->getAttribute('orders');
                $buckets[$key]['revenue'] += (int) $row->getAttribute('revenue');
            }
        }

        return collect($buckets)->values()->map(fn (array $b) => [
            'period' => $b['period'],
            'label' => $b['label'],
            'orders' => $b['orders'],
            'revenue' => $b['revenue'],
            'average' => $b['orders'] > 0 ? intdiv($b['revenue'], $b['orders']) : 0,
        ]);
    }

    private function periodLabel(string $interval, CarbonImmutable $date): string
    {
        return match ($interval) {
            'week' => $date->startOfWeek()->format('d M Y'),
            'month' => $date->format('M Y'),
            default => $date->format('d M Y'),
        };
    }

    /**
     * @return Collection<int, array{product_id: int, name: string, category: string, units: int, revenue: int}>
     */
    public function products(): Collection
    {
        return $this->products ??= $this->loadProducts();
    }

    /**
     * @return Collection<int, array{product_id: int, name: string, category: string, units: int, revenue: int}>
     */
    private function loadProducts(): Collection
    {
        $rows = $this->sales()
            ->selectRaw('product_id, max(product_name) as name, sum(quantity) as units, sum(total) as revenue')
            ->groupBy('product_id')
            ->get();

        $products = Product::query()->with('category')->whereIn('id', $rows->pluck('product_id'))->get()->keyBy('id');

        return $rows
            ->map(fn (Order $r) => [
                'product_id' => (int) $r->product_id,
                'name' => (string) $r->getAttribute('name'),
                'category' => $products->get($r->product_id)?->category->name ?? 'Tanpa kategori',
                'units' => (int) $r->getAttribute('units'),
                'revenue' => (int) $r->getAttribute('revenue'),
            ])
            ->sortBy([['units', 'desc'], ['revenue', 'desc']])
            ->values();
    }

    /**
     * @return Collection<int, array{category: string, units: int, revenue: int}>
     */
    public function categories(): Collection
    {
        return $this->products()
            ->groupBy('category')
            ->map(fn (Collection $items, string $category) => [
                'category' => $category,
                'units' => (int) $items->sum('units'),
                'revenue' => (int) $items->sum('revenue'),
            ])
            ->sortBy([['units', 'desc'], ['revenue', 'desc']])
            ->values();
    }

    /**
     * @return Collection<int, array{code: string, uses: int, discount: int, revenue: int}>
     */
    public function coupons(): Collection
    {
        return $this->sales()
            ->whereNotNull('coupon_code')
            ->selectRaw('coupon_code as code, count(*) as uses, sum(discount) as discount, sum(total) as revenue')
            ->groupBy('coupon_code')
            ->orderByDesc('uses')
            ->orderByDesc('discount')
            ->get()
            ->map(fn (Order $r) => [
                'code' => (string) $r->getAttribute('code'),
                'uses' => (int) $r->getAttribute('uses'),
                'discount' => (int) $r->getAttribute('discount'),
                'revenue' => (int) $r->getAttribute('revenue'),
            ]);
    }

    /**
     * Customers are identified by (case-insensitive) email. "New" = first ever paid order falls in the range;
     * repeat rate = share of customers active in the range with two or more orders in it.
     *
     * @return array{new: int, active: int, repeat: int, repeat_rate: float}
     */
    public function customerSummary(): array
    {
        $new = Order::query()->sales()
            ->selectRaw('lower(email) as customer')
            ->groupBy('customer')
            ->havingRaw('min(paid_at) between ? and ?', [
                $this->range->from->toDateTimeString(),
                $this->range->to->toDateTimeString(),
            ])
            ->toBase()
            ->getCountForPagination();

        $perCustomer = $this->sales()
            ->selectRaw('lower(email) as customer, count(*) as orders')
            ->groupBy('customer')
            ->get();

        $active = $perCustomer->count();
        $repeat = $perCustomer->where('orders', '>=', 2)->count();

        return [
            'new' => $new,
            'active' => $active,
            'repeat' => $repeat,
            'repeat_rate' => $active > 0 ? round($repeat / $active * 100, 1) : 0.0,
        ];
    }

    /**
     * @return Collection<int, array{email: string, name: string, orders: int, spend: int}>
     */
    public function topCustomers(?int $limit = 10): Collection
    {
        return $this->sales()
            ->selectRaw('lower(email) as customer, max(customer_name) as name, count(*) as orders, sum(total) as spend')
            ->groupBy('customer')
            ->orderByDesc('spend')
            ->orderBy('customer')
            ->when($limit, fn (Builder $query, int $limit) => $query->limit($limit))
            ->get()
            ->map(fn (Order $r) => [
                'email' => (string) $r->getAttribute('customer'),
                'name' => (string) $r->getAttribute('name'),
                'orders' => (int) $r->getAttribute('orders'),
                'spend' => (int) $r->getAttribute('spend'),
            ]);
    }

    /**
     * Orders placed in the range, by status (pending/cancelled orders have no paid_at, so created_at is used).
     *
     * @return Collection<int, array{status: OrderStatus, orders: int, value: int}>
     */
    public function statuses(): Collection
    {
        $rows = Order::query()
            ->whereBetween('created_at', [$this->range->from, $this->range->to])
            ->selectRaw('status, count(*) as orders, sum(total) as value')
            ->groupBy('status')
            ->get()
            ->keyBy(fn (Order $r) => $r->status->value);

        return collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
            'status' => $status,
            'orders' => (int) $rows->get($status->value)?->getAttribute('orders'),
            'value' => (int) $rows->get($status->value)?->getAttribute('value'),
        ]);
    }
}
