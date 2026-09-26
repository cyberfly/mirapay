@props(['label' => null])

<div {{ $attributes->whereDoesntStartWith('wire:model') }}>
    @if ($label)
        <flux:label class="mb-2">{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        x-data="richEditor(@js($this->form->description ?? ''))"
        x-modelable="content"
        {{ $attributes->whereStartsWith('wire:model') }}
        class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
    >
        <div class="flex flex-wrap gap-1 border-b border-zinc-200 p-1 dark:border-zinc-700">
            @foreach ([
                ['Tebal', 'bold', 'toggleBold', 'B', 'font-bold', ''],
                ['Condong', 'italic', 'toggleItalic', 'I', 'italic', ''],
                ['Garis bawah', 'underline', 'toggleUnderline', 'U', 'underline', ''],
                ['Tajuk 2', 'heading', 'toggleHeading', 'H2', '', '{ level: 2 }'],
                ['Tajuk 3', 'heading', 'toggleHeading', 'H3', '', '{ level: 3 }'],
                ['Senarai', 'bulletList', 'toggleBulletList', '• Senarai', '', ''],
                ['Senarai bernombor', 'orderedList', 'toggleOrderedList', '1. Senarai', '', ''],
                ['Petikan', 'blockquote', 'toggleBlockquote', '“ ”', '', ''],
            ] as [$title, $name, $command, $text, $class, $args])
                <button type="button" title="{{ $title }}"
                    x-on:click="editor.chain().focus().{{ $command }}({{ $args }}).run()"
                    x-bind:class="isActive('{{ $name }}'{{ $args !== '' ? ', '.$args : '' }}) ? 'bg-sky-100 text-sky-700' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800'"
                    class="rounded px-2 py-1 text-sm {{ $class }}">{{ $text }}</button>
            @endforeach
            <button type="button" title="Pautan" x-on:click="setLink()"
                x-bind:class="isActive('link') ? 'bg-sky-100 text-sky-700' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800'"
                class="rounded px-2 py-1 text-sm">Pautan</button>
        </div>

        <div x-ref="editor"></div>
    </div>

    @error($attributes->whereStartsWith('wire:model')->first())
        <flux:error name="{{ $attributes->whereStartsWith('wire:model')->first() }}" />
    @enderror
</div>
