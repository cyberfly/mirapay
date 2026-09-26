@props(['form', 'currentImage' => null])

<div class="space-y-4">
    <flux:input wire:model="form.name" label="Nama produk" />
    <flux:input wire:model="form.slug" label="Slug (URL: /p/slug)" />
    <flux:select wire:model="form.category_id" label="Kategori">
        <flux:select.option value="">Tiada kategori</flux:select.option>
        @foreach (\App\Models\Category::orderBy('name')->get() as $category)
            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
        @endforeach
    </flux:select>
    <flux:input wire:model="form.tagline" label="Tagline (pilihan)" />
    <x-admin.rich-editor wire:model="form.description" label="Deskripsi" />

    <div class="grid grid-cols-2 gap-4">
        <flux:input wire:model="form.price" label="Harga (RM)" inputmode="decimal" />
        <flux:input wire:model="form.compare_at_price" label="Harga asal dicoret (RM, pilihan)" inputmode="decimal" />
    </div>

    <flux:input wire:model="form.stock" type="number" min="0" label="Stok (kosong = tanpa had)" />

    <flux:switch wire:model.live="form.requires_shipping" label="Produk fizikal (perlu penghantaran)" />
    @if ($form->requires_shipping)
        <flux:input wire:model="form.shipping_fee" label="Caj pos (RM, sekali per pesanan)" inputmode="decimal" />
    @endif

    <flux:switch wire:model="form.is_active" label="Aktif" />
    <flux:switch wire:model="form.is_featured" label="Produk utama (dipaparkan di laman utama)" />

    <div>
        <flux:input type="file" wire:model="form.image" label="Gambar utama" accept="image/*" />
        @if ($currentImage)
            <img src="{{ $currentImage }}" alt="" class="mt-2 h-24 rounded-lg">
        @endif
    </div>
</div>
