<?php

use App\Concerns\HasReportRange;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Laporan produk & kategori')] class extends Component {
    use HasReportRange;

    #[Computed]
    public function products()
    {
        return $this->reports()->products();
    }

    #[Computed]
    public function categories()
    {
        return $this->reports()->categories();
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">Laporan produk &amp; kategori</flux:heading>

    <x-admin.report-filter report="products" :params="$this->exportParams()" />

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-2">
            <flux:heading size="lg">Produk terlaris</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Produk</flux:table.column>
                    <flux:table.column>Kuantiti</flux:table.column>
                    <flux:table.column>Hasil</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->products as $row)
                        <flux:table.row :key="$row['product_id']">
                            <flux:table.cell>{{ $row['name'] }} <span class="text-zinc-500">({{ $row['category'] }})</span></flux:table.cell>
                            <flux:table.cell>{{ $row['units'] }}</flux:table.cell>
                            <flux:table.cell>{{ Money::format($row['revenue']) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="3">Tiada jualan dalam tempoh ini.</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="space-y-2">
            <flux:heading size="lg">Kategori</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Kategori</flux:table.column>
                    <flux:table.column>Kuantiti</flux:table.column>
                    <flux:table.column>Hasil</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->categories as $row)
                        <flux:table.row :key="$row['category']">
                            <flux:table.cell>{{ $row['category'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['units'] }}</flux:table.cell>
                            <flux:table.cell>{{ Money::format($row['revenue']) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="3">Tiada jualan dalam tempoh ini.</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</section>
