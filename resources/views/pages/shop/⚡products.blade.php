<?php

use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::shop'), Title('Semua Produk')] class extends Component {
    use WithPagination;

    #[Url(as: 'kategori')]
    public string $category = '';

    #[Url(as: 'cari')]
    public string $search = '';

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function selectCategory(string $slug): void
    {
        $this->category = $this->category === $slug ? '' : $slug;
        $this->resetPage();
    }

    #[Computed]
    public function categories()
    {
        return Category::whereHas('products', fn ($q) => $q->active())->orderBy('name')->get();
    }

    #[Computed]
    public function products()
    {
        return Product::active()
            ->with('category')
            ->when($this->category !== '', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $this->category)))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->latest()
            ->paginate(12);
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="text-3xl font-bold text-sky-800 dark:text-sky-300">Semua produk</h1>
        <div class="sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari produk" icon="magnifying-glass" />
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="$set('category', '')" class="rounded-full border px-4 py-1.5 text-sm font-medium transition {{ $category === '' ? 'border-sky-600 bg-sky-600 text-white' : 'border-sky-200 bg-white text-sky-700 hover:bg-sky-100' }}">Semua</button>
        @foreach ($this->categories as $item)
            <button type="button" wire:key="cat-{{ $item->id }}" wire:click="selectCategory('{{ $item->slug }}')" class="rounded-full border px-4 py-1.5 text-sm font-medium transition {{ $category === $item->slug ? 'border-sky-600 bg-sky-600 text-white' : 'border-sky-200 bg-white text-sky-700 hover:bg-sky-100' }}">{{ $item->name }}</button>
        @endforeach
    </div>

    @if ($this->products->isEmpty())
        <p class="rounded-2xl border border-dashed border-sky-200 bg-white p-8 text-center text-slate-500">Tiada produk dijumpai.</p>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->products as $product)
                <x-shop.product-card :product="$product" wire:key="prod-{{ $product->id }}" />
            @endforeach
        </div>

        <div>{{ $this->products->links() }}</div>
    @endif
</div>
