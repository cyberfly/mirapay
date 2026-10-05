<?php

use App\Concerns\HasReportRange;
use App\Support\Money;
use App\Support\Reports\Reports;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Laporan jualan')] class extends Component {
    use HasReportRange;

    #[Url]
    public string $interval = 'day';

    public function updatedInterval(): void
    {
        if (! in_array($this->interval, Reports::INTERVALS, true)) {
            $this->interval = 'day';
        }
    }

    #[Computed]
    public function summary(): array
    {
        return $this->reports()->salesSummary();
    }

    #[Computed]
    public function rows()
    {
        return $this->reports()->salesByPeriod($this->interval);
    }

    #[Computed]
    public function series(): array
    {
        return [
            'labels' => $this->rows->pluck('label')->all(),
            'values' => $this->rows->map(fn (array $row) => $row['revenue'] / 100)->all(),
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">Laporan jualan</flux:heading>
    <flux:text>Hanya pesanan Dibayar/Dihantar. Hasil = jumlah pesanan (selepas diskaun, termasuk penghantaran), mengikut tarikh bayaran.</flux:text>

    <x-admin.report-filter report="sales" :params="$this->exportParams() + ['interval' => $interval]">
        <flux:select wire:model.live="interval" label="Kumpulan" class="sm:w-40">
            <flux:select.option value="day">Harian</flux:select.option>
            <flux:select.option value="week">Mingguan</flux:select.option>
            <flux:select.option value="month">Bulanan</flux:select.option>
        </flux:select>
    </x-admin.report-filter>

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Jumlah jualan', Money::format($this->summary['revenue'])],
            ['Pesanan', $this->summary['orders']],
            ['Purata nilai pesanan', Money::format($this->summary['average'])],
        ] as [$label, $value])
            <x-admin.stat-card :$label :$value />
        @endforeach
    </div>

    <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
        <flux:heading size="lg" class="mb-4">Trend jualan</flux:heading>
        <div class="h-72" wire:key="sales-chart-{{ $from }}-{{ $to }}-{{ $interval }}" wire:ignore x-data="salesChart(@js($this->series))">
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Tempoh</flux:table.column>
            <flux:table.column>Pesanan</flux:table.column>
            <flux:table.column>Jualan</flux:table.column>
            <flux:table.column>Purata</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->rows as $row)
                <flux:table.row :key="$row['period']">
                    <flux:table.cell>{{ $row['label'] }}</flux:table.cell>
                    <flux:table.cell>{{ $row['orders'] }}</flux:table.cell>
                    <flux:table.cell>{{ Money::format($row['revenue']) }}</flux:table.cell>
                    <flux:table.cell>{{ Money::format($row['average']) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
