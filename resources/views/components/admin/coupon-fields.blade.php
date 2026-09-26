@props(['form', 'products'])

<div class="space-y-4">
    <flux:input wire:model="form.code" label="Kod kupon" />

    <div class="grid grid-cols-2 gap-4">
        <flux:select wire:model.live="form.type" label="Jenis">
            @foreach (\App\Enums\CouponType::cases() as $type)
                <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model="form.value" label="{{ $form->type === 'percent' ? 'Nilai (1-100)' : 'Nilai (RM)' }}" />
    </div>

    <flux:select wire:model="form.product_id" label="Produk">
        <flux:select.option value="">Semua produk</flux:select.option>
        @foreach ($products as $product)
            <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:input wire:model="form.max_uses" type="number" min="1" label="Had penggunaan (kosong = tanpa had)" />

    <div class="grid grid-cols-2 gap-4">
        <flux:input wire:model="form.starts_at" type="datetime-local" label="Mula (pilihan)" />
        <flux:input wire:model="form.expires_at" type="datetime-local" label="Tamat (pilihan)" />
    </div>

    <flux:switch wire:model="form.is_active" label="Aktif" />
</div>
