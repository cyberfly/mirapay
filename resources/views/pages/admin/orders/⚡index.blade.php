<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pesanan')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('admin');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->when(OrderStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.trim($this->search).'%';

                $query->where(fn ($q) => $q
                    ->where('order_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->latest()
            ->paginate(15);
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">Pesanan</flux:heading>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nombor pesanan, nama atau emel" icon="magnifying-glass" class="flex-1" />
        <flux:select wire:model.live="status" class="sm:w-52">
            <flux:select.option value="">Semua status</flux:select.option>
            @foreach (OrderStatus::cases() as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->orders">
        <flux:table.columns>
            <flux:table.column>No. pesanan</flux:table.column>
            <flux:table.column>Pelanggan</flux:table.column>
            <flux:table.column>Produk</flux:table.column>
            <flux:table.column>Jumlah</flux:table.column>
            <flux:table.column>Status</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->orders as $order)
                <flux:table.row :key="$order->id">
                    <flux:table.cell><a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="font-mono hover:underline">{{ $order->order_number }}</a></flux:table.cell>
                    <flux:table.cell>{{ $order->customer_name }}</flux:table.cell>
                    <flux:table.cell>{{ $order->product_name }} &times; {{ $order->quantity }}</flux:table.cell>
                    <flux:table.cell>{{ Money::format($order->total) }}</flux:table.cell>
                    <flux:table.cell><flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge></flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
