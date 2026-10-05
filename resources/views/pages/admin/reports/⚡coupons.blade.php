<?php

use App\Concerns\HasReportRange;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Laporan kupon')] class extends Component {
    use HasReportRange;

    #[Computed]
    public function coupons()
    {
        return $this->reports()->coupons();
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">Laporan kupon</flux:heading>
    <flux:text>Penggunaan pada pesanan Dibayar/Dihantar mengikut tarikh bayaran.</flux:text>

    <x-admin.report-filter report="coupons" :params="$this->exportParams()" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Kod</flux:table.column>
            <flux:table.column>Penggunaan</flux:table.column>
            <flux:table.column>Diskaun diberi</flux:table.column>
            <flux:table.column>Hasil</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->coupons as $row)
                <flux:table.row :key="$row['code']">
                    <flux:table.cell class="font-mono">{{ $row['code'] }}</flux:table.cell>
                    <flux:table.cell>{{ $row['uses'] }}</flux:table.cell>
                    <flux:table.cell>{{ Money::format($row['discount']) }}</flux:table.cell>
                    <flux:table.cell>{{ Money::format($row['revenue']) }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="4">Tiada kupon digunakan dalam tempoh ini.</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
