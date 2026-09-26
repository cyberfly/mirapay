<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Payments\PaymentGateway;
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

    public function pay(): void
    {
        $this->order->refresh();

        if ($this->order->status !== OrderStatus::Pending || $this->order->payment_gateway !== 'stripe') {
            return;
        }

        $this->redirect(app(PaymentGateway::class)->checkoutUrl($this->order));
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6" @if ($order->status === OrderStatus::Pending && $order->payment_gateway === 'stripe') wire:poll.3s @endif>
    <div class="flex flex-col gap-4 rounded-3xl bg-linear-to-br from-sky-50 via-white to-indigo-50 p-6 ring-1 ring-slate-200/70 sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div>
            <p class="text-sm font-medium text-slate-500">Nombor pesanan</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">{{ $order->order_number }}</h1>
        </div>
        <flux:badge size="lg" :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
    </div>

    @if ($order->status === OrderStatus::Pending && $order->payment_gateway === 'stripe')
        <flux:callout variant="warning" icon="clock" heading="Menunggu pengesahan bayaran" text="Jika anda telah membayar, halaman ini akan dikemas kini secara automatik. Jika belum, sila teruskan pembayaran.">
            <x-slot name="actions">
                <flux:button wire:click="pay" variant="primary" size="sm">Bayar sekarang</flux:button>
            </x-slot>
        </flux:callout>
    @elseif ($order->status === OrderStatus::Pending)
        <flux:callout variant="warning" icon="clock" heading="Pembayaran belum diterima" text="Pesanan anda telah direkodkan. Kami akan mengesahkan bayaran secara manual." />
    @endif

    <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Ringkasan</h2>
        <dl class="space-y-2 text-sm text-slate-600">
            <div class="flex justify-between gap-4"><dt>{{ $order->product_name }} &times; {{ $order->quantity }}</dt><dd class="text-slate-900">{{ Money::format($order->subtotal) }}</dd></div>
            @if ($order->discount > 0)
                <div class="flex justify-between gap-4 text-emerald-600"><dt>Diskaun ({{ $order->coupon_code }})</dt><dd>-{{ Money::format($order->discount) }}</dd></div>
            @endif
            @if ($order->requires_shipping)
                <div class="flex justify-between gap-4"><dt>Pos</dt><dd class="text-slate-900">{{ Money::format($order->shipping_fee) }}</dd></div>
            @endif
            <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 text-base font-semibold text-slate-900"><dt>Jumlah</dt><dd>{{ Money::format($order->total) }}</dd></div>
        </dl>
    </div>

    <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 text-sm shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Pelanggan</h2>
        <div class="space-y-1 text-slate-600">
            <p class="font-medium text-slate-900">{{ $order->customer_name }}</p>
            <p>{{ $order->email }} &middot; {{ $order->phone }}</p>
        </div>
        @if ($order->requires_shipping)
            <div class="space-y-1 border-t border-slate-200 pt-4 text-slate-600">
                <p class="font-medium text-slate-900">Alamat penghantaran</p>
                <p>{{ $order->address_line1 }}@if ($order->address_line2), {{ $order->address_line2 }}@endif</p>
                <p>{{ $order->postcode }} {{ $order->city }}, {{ $order->stateLabel() }}</p>
            </div>
        @endif
    </div>

    <div class="text-center">
        <a href="{{ route('shop.products.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-sky-600 transition hover:text-sky-700">
            <flux:icon.arrow-left variant="micro" />
            Teruskan membeli-belah
        </a>
    </div>
</div>
