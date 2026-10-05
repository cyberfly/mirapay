<?php

use App\Actions\Subscriptions\CancelSubscription;
use App\Actions\Subscriptions\PauseSubscription;
use App\Actions\Subscriptions\ResumeSubscription;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::shop', ['noindex' => true]), Title('Langganan')] class extends Component {
    #[Locked]
    public Subscription $subscription;

    public function mount(Subscription $subscription): void
    {
        $this->subscription = $subscription;
    }

    public function pause(): void
    {
        $this->change(fn () => app(PauseSubscription::class)($this->subscription), 'Langganan dijeda.');
    }

    public function resume(): void
    {
        $this->change(fn () => app(ResumeSubscription::class)($this->subscription), 'Langganan disambung semula.');
    }

    public function cancel(): void
    {
        $this->change(fn () => app(CancelSubscription::class)($this->subscription), 'Langganan dibatalkan.');

        Flux::modal('cancel-subscription')->close();
    }

    protected function change(Closure $callback, string $message): void
    {
        $this->subscription->refresh();

        try {
            $callback();
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        } finally {
            $this->subscription->refresh();
        }

        Flux::toast(variant: 'success', text: $message);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    <div class="flex flex-col gap-4 rounded-3xl bg-linear-to-br from-sky-50 via-white to-indigo-50 p-6 ring-1 ring-slate-200/70 sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div>
            <p class="text-sm font-medium text-slate-500">Langganan</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">{{ $subscription->product_name }}</h1>
        </div>
        <flux:badge size="lg" :color="$subscription->status->color()">{{ $subscription->status->label() }}</flux:badge>
    </div>

    @if ($subscription->status === SubscriptionStatus::PastDue)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="Bayaran tertunggak" text="Sila jelaskan bayaran pada pesanan di bawah supaya langganan anda tidak tamat." />
    @elseif ($subscription->status === SubscriptionStatus::Cancelled && $subscription->ends_at?->isFuture())
        <flux:callout variant="warning" icon="information-circle" :heading="'Aktif sehingga '.$subscription->ends_at->format('d/m/Y')" text="Langganan ini tidak akan diperbaharui lagi." />
    @elseif ($subscription->status === SubscriptionStatus::Pending)
        <flux:callout variant="warning" icon="clock" heading="Menunggu bayaran pertama" text="Langganan akan bermula selepas bayaran pertama diterima." />
    @endif

    <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Butiran</h2>
        <dl class="space-y-2 text-sm text-slate-600">
            <div class="flex justify-between gap-4"><dt>Pelan</dt><dd class="text-slate-900">{{ $subscription->billing_interval->label() }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ $subscription->product_name }} &times; {{ $subscription->quantity }}</dt><dd class="text-slate-900">{{ Money::format($subscription->renewalTotal()) }} / {{ $subscription->billing_interval->unit() }}</dd></div>
            @if ($subscription->current_period_end)
                <div class="flex justify-between gap-4"><dt>Tempoh semasa</dt><dd class="text-slate-900">{{ $subscription->current_period_start->format('d/m/Y') }} &ndash; {{ $subscription->current_period_end->format('d/m/Y') }}</dd></div>
            @endif
            @if ($subscription->next_billing_date)
                <div class="flex justify-between gap-4"><dt>Bayaran seterusnya</dt><dd class="text-slate-900">{{ $subscription->next_billing_date->format('d/m/Y') }}</dd></div>
            @endif
        </dl>

        @if ($subscription->canCancel())
            <div class="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                @if ($subscription->status === SubscriptionStatus::Active)
                    <flux:button wire:click="pause">Jeda langganan</flux:button>
                @elseif ($subscription->status === SubscriptionStatus::Paused)
                    <flux:button variant="primary" wire:click="resume">Sambung semula</flux:button>
                @endif
                <flux:modal.trigger name="cancel-subscription">
                    <flux:button variant="danger">Batalkan langganan</flux:button>
                </flux:modal.trigger>
            </div>
        @endif
    </div>

    <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Pesanan</h2>
        <ul class="divide-y divide-slate-100 text-sm">
            @foreach ($subscription->orders()->latest('id')->get() as $order)
                <li class="flex items-center justify-between gap-4 py-3">
                    <a href="{{ route('shop.orders.show', $order) }}" class="font-mono text-sky-600 hover:underline">{{ $order->order_number }}</a>
                    <span class="text-slate-900">{{ Money::format($order->total) }}</span>
                    <flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
                </li>
            @endforeach
        </ul>
    </div>

    <flux:modal name="cancel-subscription" class="min-w-88">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Batalkan langganan?</flux:heading>
                <flux:text class="mt-2">Tiada bayaran seterusnya akan dikenakan. Anda masih boleh menggunakannya sehingga tempoh yang telah dibayar tamat.</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="cancel">Ya, batalkan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
