<?php

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Langganan')] class extends Component {
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
    public function subscriptions()
    {
        return Subscription::query()
            ->when(SubscriptionStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.trim($this->search).'%';

                $query->where(fn ($q) => $q
                    ->where('customer_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('product_name', 'like', $term));
            })
            ->latest()
            ->paginate(15);
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">Langganan</flux:heading>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama, emel atau produk" icon="magnifying-glass" class="flex-1" />
        <flux:select wire:model.live="status" class="sm:w-52">
            <flux:select.option value="">Semua status</flux:select.option>
            @foreach (SubscriptionStatus::cases() as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->subscriptions">
        <flux:table.columns>
            <flux:table.column>Pelanggan</flux:table.column>
            <flux:table.column>Produk</flux:table.column>
            <flux:table.column>Jumlah</flux:table.column>
            <flux:table.column>Bayaran seterusnya</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->subscriptions as $subscription)
                <flux:table.row :key="$subscription->id">
                    <flux:table.cell>{{ $subscription->customer_name }}<div class="text-xs text-zinc-500">{{ $subscription->email }}</div></flux:table.cell>
                    <flux:table.cell>{{ $subscription->product_name }} &times; {{ $subscription->quantity }}</flux:table.cell>
                    <flux:table.cell>{{ Money::format($subscription->renewalTotal()) }} / {{ $subscription->billing_interval->unit() }}</flux:table.cell>
                    <flux:table.cell>{{ $subscription->next_billing_date?->format('d/m/Y') ?? '-' }}</flux:table.cell>
                    <flux:table.cell><flux:badge :color="$subscription->status->color()">{{ $subscription->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="text-end"><flux:button size="sm" :href="route('admin.subscriptions.show', $subscription)" wire:navigate>Lihat</flux:button></flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
