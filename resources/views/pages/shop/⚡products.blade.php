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

<div class="space-y-8">
    <div class="flex flex-col gap-6 border-b border-slate-200 pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-sky-600">Kedai</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">Semua produk</h1>
        </div>
        <div class="sm:w-80">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari produk" icon="magnifying-glass" />
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="$set('category', '')" class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $category === '' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700' }}">Semua</button>
        @foreach ($this->categories as $item)
            <button type="button" wire:key="cat-{{ $item->id }}" wire:click="selectCategory('{{ $item->slug }}')" class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $category === $item->slug ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700' }}">{{ $item->name }}</button>
        @endforeach
    </div>

    @if ($this->products->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-12 text-center">
            <flux:icon.magnifying-glass class="size-8 text-slate-300" />
            <p class="text-slate-500">Tiada produk dijumpai.</p>
        </div>
    @else
        <div class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->products as $product)
                <x-shop.product-card :product="$product" wire:key="prod-{{ $product->id }}" />
            @endforeach
        </div>

        <div class="pt-4">{{ $this->products->links() }}</div>
    @endif
</div>
