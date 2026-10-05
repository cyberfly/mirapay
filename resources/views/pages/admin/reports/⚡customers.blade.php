<?php

use App\Concerns\HasReportRange;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Laporan pelanggan')] class extends Component {
    use HasReportRange;

    #[Computed]
    public function summary(): array
    {
        return $this->reports()->customerSummary();
    }

    #[Computed]
    public function top()
    {
        return $this->reports()->topCustomers();
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">Laporan pelanggan</flux:heading>
    <flux:text>Pelanggan dikenal pasti melalui emel. Pelanggan baharu = pembelian berbayar pertama jatuh dalam tempoh ini. Kadar ulangan = peratus pelanggan aktif dengan 2 pesanan atau lebih dalam tempoh ini.</flux:text>

    <x-admin.report-filter report="customers" :params="$this->exportParams()" />

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Pelanggan baharu', $this->summary['new']],
            ['Pelanggan aktif', $this->summary['active']],
            ['Kadar ulangan', $this->summary['repeat_rate'].'%'],
        ] as [$label, $value])
            <x-admin.stat-card :$label :$value />
        @endforeach
    </div>

    <flux:heading size="lg">Pelanggan teratas (perbelanjaan)</flux:heading>
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Pelanggan</flux:table.column>
            <flux:table.column>Pesanan</flux:table.column>
            <flux:table.column>Perbelanjaan</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->top as $row)
                <flux:table.row :key="$row['email']">
                    <flux:table.cell>{{ $row['name'] }} <span class="text-zinc-500">{{ $row['email'] }}</span></flux:table.cell>
                    <flux:table.cell>{{ $row['orders'] }}</flux:table.cell>
                    <flux:table.cell>{{ Money::format($row['spend']) }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="3">Tiada jualan dalam tempoh ini.</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
