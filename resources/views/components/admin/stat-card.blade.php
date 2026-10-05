@props(['label', 'value'])

<div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
    <flux:text>{{ $label }}</flux:text>
    <div class="mt-1 text-2xl font-bold text-sky-700 dark:text-sky-400">{{ $value }}</div>
</div>
