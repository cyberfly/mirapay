<?php

use App\Models\Order;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::shop', ['noindex' => true]), Title('Pesanan')] class extends Component {
    #[Locked]
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:text>Nombor pesanan</flux:text>
            <flux:heading size="xl" level="1">{{ $order->order_number }}</flux:heading>
        </div>
        <flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
    </div>

    @if ($order->status === \App\Enums\OrderStatus::Pending)
        <flux:callout variant="warning" icon="clock" heading="Pembayaran belum diterima" text="Pesanan anda telah direkodkan. Kami akan mengesahkan bayaran secara manual." />
    @endif

    <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="lg">Ringkasan</flux:heading>
        <dl class="space-y-1 text-sm">
            <div class="flex justify-between"><dt>{{ $order->product_name }} &times; {{ $order->quantity }}</dt><dd>{{ Money::format($order->subtotal) }}</dd></div>
            @if ($order->discount > 0)
                <div class="flex justify-between text-green-600"><dt>Diskaun ({{ $order->coupon_code }})</dt><dd>-{{ Money::format($order->discount) }}</dd></div>
            @endif
            @if ($order->requires_shipping)
                <div class="flex justify-between"><dt>Pos</dt><dd>{{ Money::format($order->shipping_fee) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold dark:border-zinc-700"><dt>Jumlah</dt><dd>{{ Money::format($order->total) }}</dd></div>
        </dl>
    </div>

    <div class="space-y-1 rounded-xl border border-zinc-200 bg-white p-5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="lg">Pelanggan</flux:heading>
        <p>{{ $order->customer_name }}</p>
        <p>{{ $order->email }} &middot; {{ $order->phone }}</p>
        @if ($order->requires_shipping)
            <p class="pt-2 font-medium">Alamat penghantaran</p>
            <p>{{ $order->address_line1 }}@if ($order->address_line2), {{ $order->address_line2 }}@endif</p>
            <p>{{ $order->postcode }} {{ $order->city }}, {{ $order->stateLabel() }}</p>
        @endif
    </div>
</div>
