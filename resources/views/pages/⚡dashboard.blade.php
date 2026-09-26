<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Dashboard')] class extends Component {
    #[Url]
    public int $days = 30;

    public function updatedDays(): void
    {
        if (! in_array($this->days, [7, 30, 90], true)) {
            $this->days = 30;
        }
    }

    protected function rangeStart()
    {
        $days = in_array($this->days, [7, 30, 90], true) ? $this->days : 30;

        return now()->subDays($days - 1)->startOfDay();
    }

    protected function sales()
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Shipped])
            ->where('paid_at', '>=', $this->rangeStart());
    }

    /**
     * @return array{revenue: int, orders: int, average: int, pending: int}
     */
    #[Computed]
    public function summary(): array
    {
        $revenue = (int) $this->sales()->sum('total');
        $orders = $this->sales()->count();

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'average' => $orders > 0 ? intdiv($revenue, $orders) : 0,
            'pending' => Order::where('status', OrderStatus::Pending)->count(),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    #[Computed]
    public function series(): array
    {
        $totals = $this->sales()
            ->select(DB::raw('date(paid_at) as day'), DB::raw('sum(total) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];

        for ($date = $this->rangeStart(); $date->lte(now()); $date = $date->addDay()) {
            $labels[] = $date->format('d M');
            $values[] = ((int) ($totals[$date->toDateString()] ?? 0)) / 100;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    #[Computed]
    public function topProducts()
    {
        return $this->sales()
            ->select('product_name', DB::raw('sum(quantity) as units'), DB::raw('sum(total) as revenue'))
            ->groupBy('product_name')
            ->orderByDesc('units')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentOrders()
    {
        return Order::latest()->limit(5)->get();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <flux:heading size="xl" level="1">Dashboard</flux:heading>

    @can('admin')
        <div class="flex items-center justify-between gap-4">
            <flux:text>Jualan daripada pesanan yang telah dibayar atau dihantar.</flux:text>
            <flux:select wire:model.live="days" class="max-w-40">
                <flux:select.option value="7">7 hari</flux:select.option>
                <flux:select.option value="30">30 hari</flux:select.option>
                <flux:select.option value="90">90 hari</flux:select.option>
            </flux:select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Jumlah jualan', Money::format($this->summary['revenue'])],
                ['Pesanan dibayar', $this->summary['orders']],
                ['Purata nilai pesanan', Money::format($this->summary['average'])],
                ['Menunggu bayaran', $this->summary['pending']],
            ] as [$label, $value])
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
                    <flux:text>{{ $label }}</flux:text>
                    <div class="mt-1 text-2xl font-bold text-sky-700 dark:text-sky-400">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading size="lg" class="mb-4">Graf jualan harian</flux:heading>
            <div class="h-72" wire:key="sales-chart-{{ $days }}" wire:ignore x-data="salesChart(@js($this->series))">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
                <flux:heading size="lg" class="mb-3">Produk terlaris</flux:heading>
                @forelse ($this->topProducts as $row)
                    <div class="flex items-center justify-between border-t border-neutral-100 py-2 text-sm first:border-t-0 dark:border-neutral-800">
                        <span>{{ $row->product_name }} <span class="text-zinc-500">&times; {{ $row->units }}</span></span>
                        <span class="font-medium">{{ Money::format((int) $row->revenue) }}</span>
                    </div>
                @empty
                    <flux:text>Belum ada jualan dalam tempoh ini.</flux:text>
                @endforelse
            </div>

            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
                <flux:heading size="lg" class="mb-3">Pesanan terkini</flux:heading>
                @forelse ($this->recentOrders as $order)
                    <div class="flex items-center justify-between gap-2 border-t border-neutral-100 py-2 text-sm first:border-t-0 dark:border-neutral-800">
                        <div>
                            <div class="font-mono">{{ $order->order_number }}</div>
                            <div class="text-zinc-500">{{ $order->customer_name }} &middot; {{ Money::format($order->total) }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
                            <flux:button size="xs" :href="route('admin.orders.show', $order)" wire:navigate>Lihat</flux:button>
                        </div>
                    </div>
                @empty
                    <flux:text>Belum ada pesanan.</flux:text>
                @endforelse
            </div>
        </div>
    @else
        <flux:text>Selamat datang, {{ auth()->user()->name }}.</flux:text>
    @endcan
</div>
