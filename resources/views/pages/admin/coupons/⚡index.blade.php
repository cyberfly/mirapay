<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Kupon')] class extends Component {
    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('admin');
    }

    #[Computed]
    public function coupons()
    {
        return Coupon::with('product')
            ->when(trim($this->search) !== '', fn ($query) => $query->where('code', 'like', '%'.trim($this->search).'%'))
            ->latest()
            ->get();
    }

    public function delete(int $id): void
    {
        $this->authorize('admin');

        Coupon::findOrFail($id)->delete();
        unset($this->coupons);

        Flux::toast(variant: 'success', text: 'Kupon dipadam.');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">Kupon</flux:heading>
        <flux:button variant="primary" :href="route('admin.coupons.create')" wire:navigate>Kupon baru</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kod kupon" icon="magnifying-glass" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Kod</flux:table.column>
            <flux:table.column>Nilai</flux:table.column>
            <flux:table.column>Produk</flux:table.column>
            <flux:table.column>Guna</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->coupons as $coupon)
                <flux:table.row :key="$coupon->id">
                    <flux:table.cell class="font-mono">{{ $coupon->code }}</flux:table.cell>
                    <flux:table.cell>{{ $coupon->type === CouponType::Percent ? $coupon->value.'%' : Money::format($coupon->value) }}</flux:table.cell>
                    <flux:table.cell>{{ $coupon->product?->name ?? 'Semua' }}</flux:table.cell>
                    <flux:table.cell>{{ $coupon->used_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</flux:table.cell>
                    <flux:table.cell><flux:badge :color="$coupon->is_active ? 'green' : 'zinc'">{{ $coupon->is_active ? 'Aktif' : 'Tidak aktif' }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="space-x-2 text-end">
                        <flux:button size="sm" :href="route('admin.coupons.edit', $coupon)" wire:navigate>Edit</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="delete({{ $coupon->id }})" wire:confirm="Padam kupon ini?">Padam</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
