@props(['report', 'params' => []])

<div class="flex flex-col gap-3 sm:flex-row sm:items-end">
    <flux:input type="date" wire:model.change="from" label="Dari" class="sm:w-44" />
    <flux:input type="date" wire:model.change="to" label="Hingga" class="sm:w-44" />
    {{ $slot }}
    <flux:spacer />
    <flux:button icon="arrow-down-tray" :href="route('admin.reports.export', ['report' => $report] + $params)">Eksport CSV</flux:button>
</div>
