<?php

use App\Actions\Subscriptions\CancelSubscription;
use App\Models\Subscription;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Butiran langganan')] class extends Component {
    #[Locked]
    public Subscription $subscription;

    public function mount(Subscription $subscription): void
    {
        $this->authorize('admin');

        $this->subscription = $subscription;
    }

    public function cancel(): void
    {
        $this->authorize('admin');

        $this->subscription->refresh();

        try {
            app(CancelSubscription::class)($this->subscription);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        } finally {
            $this->subscription->refresh();
            Flux::modal('cancel-subscription')->close();
        }

        Flux::toast(variant: 'success', text: 'Langganan dibatalkan.');
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:text>Langganan</flux:text>
            <flux:heading size="xl" level="1">{{ $subscription->product_name }}</flux:heading>
        </div>
        <flux:badge :color="$subscription->status->color()">{{ $subscription->status->label() }}</flux:badge>
    </div>

    @if ($subscription->canCancel())
        <div>
            <flux:modal.trigger name="cancel-subscription">
                <flux:button variant="danger">Batalkan langganan</flux:button>
            </flux:modal.trigger>
        </div>
    @endif

    <div class="space-y-1 rounded-xl border border-zinc-200 p-5 text-sm dark:border-zinc-700">
        <p>{{ $subscription->billing_interval->label() }}: {{ Money::format($subscription->renewalTotal()) }} / {{ $subscription->billing_interval->unit() }} ({{ $subscription->product_name }} &times; {{ $subscription->quantity }} @ {{ Money::format($subscription->unit_price) }})</p>
        @if ($subscription->current_period_end)
            <p>Tempoh semasa: {{ $subscription->current_period_start->format('d/m/Y') }} &ndash; {{ $subscription->current_period_end->format('d/m/Y') }}</p>
        @endif
        @if ($subscription->next_billing_date)
            <p>Bayaran seterusnya: {{ $subscription->next_billing_date->format('d/m/Y') }}</p>
        @endif
        @if ($subscription->past_due_since)
            <p>Tertunggak sejak: {{ $subscription->past_due_since->format('d/m/Y') }}</p>
        @endif
        @if ($subscription->cancelled_at)
            <p>Dibatalkan pada: {{ $subscription->cancelled_at->format('d/m/Y') }}@if ($subscription->ends_at) &middot; akses hingga {{ $subscription->ends_at->format('d/m/Y') }}@endif</p>
        @endif
    </div>

    <div class="space-y-1 rounded-xl border border-zinc-200 p-5 text-sm dark:border-zinc-700">
        <p class="font-medium">{{ $subscription->customer_name }}</p>
        <p>{{ $subscription->email }} &middot; {{ $subscription->phone }}</p>
        @if ($subscription->requires_shipping)
            <p class="pt-2">{{ $subscription->address_line1 }}@if ($subscription->address_line2), {{ $subscription->address_line2 }}@endif</p>
            <p>{{ $subscription->postcode }} {{ $subscription->city }}, {{ $subscription->stateLabel() }}</p>
        @endif
    </div>

    <div class="space-y-3 rounded-xl border border-zinc-200 p-5 text-sm dark:border-zinc-700">
        <p class="font-medium">Pesanan</p>
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($subscription->orders()->latest('id')->get() as $order)
                <li class="flex items-center justify-between gap-4 py-2">
                    <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="font-mono hover:underline">{{ $order->order_number }}</a>
                    <span>{{ $order->is_renewal ? 'Pembaharuan' : 'Pertama' }}</span>
                    <span>{{ Money::format($order->total) }}</span>
                    <flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
                </li>
            @endforeach
        </ul>
    </div>

    <flux:modal name="cancel-subscription" class="min-w-88">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Batalkan langganan?</flux:heading>
                <flux:text class="mt-2">Tiada pembaharuan akan dibuat. Pelanggan kekal mempunyai akses sehingga tempoh yang telah dibayar tamat.</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="cancel">Ya, batalkan</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
