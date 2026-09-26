<?php

use App\Actions\Shop\CancelOrder;
use App\Actions\Shop\MarkOrderAsPaid;
use App\Actions\Shop\MarkOrderAsShipped;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Butiran pesanan')] class extends Component {
    #[Locked]
    public Order $order;

    public function mount(Order $order): void
    {
        $this->authorize('admin');

        $this->order = $order;
    }

    public function markAsPaid(): void
    {
        $this->authorize('admin');

        $this->applyStatusChange(fn () => app(MarkOrderAsPaid::class)($this->order), 'Pesanan ditanda sebagai dibayar.');
    }

    public function markAsShipped(): void
    {
        $this->authorize('admin');

        $this->applyStatusChange(fn () => app(MarkOrderAsShipped::class)($this->order), 'Pesanan ditanda sebagai dihantar.');
    }

    public function cancel(): void
    {
        $this->authorize('admin');

        $this->applyStatusChange(fn () => app(CancelOrder::class)($this->order), 'Pesanan dibatalkan.');

        Flux::modal('cancel-order')->close();
    }

    protected function applyStatusChange(Closure $callback, string $message): void
    {
        try {
            $callback();
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        } finally {
            $this->order->refresh();
        }

        Flux::toast(variant: 'success', text: $message);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:text>Pesanan</flux:text>
            <flux:heading size="xl" level="1" class="font-mono">{{ $order->order_number }}</flux:heading>
        </div>
        <flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
    </div>

    <div class="flex gap-2">
        @if ($order->status->canTransitionTo(OrderStatus::Paid, $order->requires_shipping))
            <flux:button variant="primary" wire:click="markAsPaid">Tanda dibayar</flux:button>
        @endif
        @if ($order->status->canTransitionTo(OrderStatus::Shipped, $order->requires_shipping))
            <flux:button variant="primary" wire:click="markAsShipped">Tanda dihantar</flux:button>
        @endif
        @if ($order->status->canTransitionTo(OrderStatus::Cancelled, $order->requires_shipping))
            <flux:modal.trigger name="cancel-order">
                <flux:button variant="danger">Batalkan</flux:button>
            </flux:modal.trigger>
        @endif
    </div>

    <div class="space-y-1 rounded-xl border border-zinc-200 p-5 text-sm dark:border-zinc-700">
        <p>{{ $order->product_name }} &times; {{ $order->quantity }} @ {{ Money::format($order->unit_price) }}</p>
        <p>Subjumlah: {{ Money::format($order->subtotal) }}</p>
        @if ($order->discount > 0)
            <p>Diskaun ({{ $order->coupon_code }}): -{{ Money::format($order->discount) }}</p>
        @endif
        @if ($order->requires_shipping)
            <p>Pos: {{ Money::format($order->shipping_fee) }}</p>
        @endif
        <p class="font-semibold">Jumlah: {{ Money::format($order->total) }}</p>
    </div>

    <div class="space-y-1 rounded-xl border border-zinc-200 p-5 text-sm dark:border-zinc-700">
        <p class="font-medium">{{ $order->customer_name }}</p>
        <p>{{ $order->email }} &middot; {{ $order->phone }}</p>
        @if ($order->requires_shipping)
            <p class="pt-2">{{ $order->address_line1 }}@if ($order->address_line2), {{ $order->address_line2 }}@endif</p>
            <p>{{ $order->postcode }} {{ $order->city }}, {{ $order->stateLabel() }}</p>
        @endif
    </div>

    <flux:modal name="cancel-order" class="min-w-88">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Batalkan pesanan?</flux:heading>
                <flux:text class="mt-2">Stok dan kupon akan dipulangkan. Tindakan ini tidak boleh dibatalkan.</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="cancel">Ya, batalkan</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
