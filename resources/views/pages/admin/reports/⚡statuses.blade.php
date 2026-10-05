<?php

use App\Concerns\HasReportRange;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Status pesanan')] class extends Component {
    use HasReportRange;

    #[Computed]
    public function statuses()
    {
        return $this->reports()->statuses();
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">Status pesanan</flux:heading>
    <flux:text>Pesanan yang dibuat dalam tempoh ini, mengikut status semasa.</flux:text>

    <x-admin.report-filter report="statuses" :params="$this->exportParams()" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->statuses as $row)
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
                <flux:badge :color="$row['status']->color()">{{ $row['status']->label() }}</flux:badge>
                <div class="mt-2 text-2xl font-bold">{{ $row['orders'] }}</div>
                <flux:text>{{ Money::format($row['value']) }}</flux:text>
            </div>
        @endforeach
    </div>
</section>
