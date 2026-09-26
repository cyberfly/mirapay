<?php

use App\Models\Product;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Produk')] class extends Component {
    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('admin');
    }

    #[Computed]
    public function products()
    {
        return Product::withCount('orders')
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.trim($this->search).'%';

                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->latest()
            ->get();
    }

    public function delete(int $id): void
    {
        $this->authorize('admin');

        $product = Product::withCount('orders')->findOrFail($id);

        if ($product->orders_count > 0) {
            Flux::toast(variant: 'danger', text: 'Produk yang sudah ada pesanan tidak boleh dipadam. Sila nyahaktifkan sahaja.');

            return;
        }

        $product->delete();
        unset($this->products);

        Flux::toast(variant: 'success', text: 'Produk dipadam.');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">Produk</flux:heading>
        <flux:button variant="primary" :href="route('admin.products.create')" wire:navigate>Produk baru</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama atau slug produk" icon="magnifying-glass" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Harga</flux:table.column>
            <flux:table.column>Stok</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->products as $product)
                <flux:table.row :key="$product->id">
                    <flux:table.cell><a href="{{ route('shop.products.show', $product) }}" target="_blank" class="hover:underline">{{ $product->name }}</a></flux:table.cell>
                    <flux:table.cell>{{ Money::format($product->price) }}</flux:table.cell>
                    <flux:table.cell>{{ $product->stock ?? '∞' }}</flux:table.cell>
                    <flux:table.cell><flux:badge :color="$product->is_active ? 'green' : 'zinc'">{{ $product->is_active ? 'Aktif' : 'Tidak aktif' }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="space-x-2 text-end">
                        <flux:button size="sm" :href="route('admin.products.edit', $product)" wire:navigate>Edit</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="delete({{ $product->id }})" wire:confirm="Padam produk ini?">Padam</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
