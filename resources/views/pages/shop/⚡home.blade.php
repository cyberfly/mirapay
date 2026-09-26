<?php

use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::shop'), Title('Laman Utama')] class extends Component {
    #[Computed]
    public function featured(): ?Product
    {
        return Product::active()->featured()->with('category')->latest()->first();
    }

    #[Computed]
    public function promotions()
    {
        return Product::active()->onPromotion()->with('category')->latest()->take(4)->get();
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
            ->latest()
            ->take(6)
            ->get();
    }
}; ?>

<div class="space-y-14">
    @if ($this->featured)
        @php($hero = $this->featured)
        <section class="overflow-hidden rounded-3xl bg-linear-to-br from-sky-500 to-sky-700 text-white shadow-lg">
            <div class="grid items-center gap-6 md:grid-cols-2">
                <div class="space-y-4 p-8 md:p-12">
                    <span class="inline-block rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide">Produk utama</span>
                    <h1 class="text-3xl font-bold md:text-4xl">{{ $hero->name }}</h1>
                    @if ($hero->tagline)
                        <p class="text-sky-50">{{ $hero->tagline }}</p>
                    @endif
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl font-bold">{{ Money::format($hero->price) }}</span>
                        @if ($hero->discountPercent())
                            <span class="text-sky-100 line-through">{{ Money::format($hero->compare_at_price) }}</span>
                        @endif
                    </div>
                    <a href="{{ route('shop.products.show', $hero) }}" class="inline-block rounded-xl bg-white px-6 py-3 font-semibold text-sky-700 shadow transition hover:bg-sky-50">Beli sekarang</a>
                </div>
                <div class="aspect-4/3 bg-sky-400/40 md:aspect-auto md:h-full">
                    @if ($hero->imageUrl())
                        <img src="{{ $hero->imageUrl() }}" alt="{{ $hero->name }}" class="size-full object-cover">
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if ($this->promotions->isNotEmpty())
        <section class="space-y-5">
            <div>
                <h2 class="text-2xl font-bold text-sky-800 dark:text-sky-300">Promosi</h2>
                <p class="text-slate-500">Tawaran istimewa untuk anda.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->promotions as $product)
                    <x-shop.product-card :product="$product" wire:key="promo-{{ $product->id }}" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="space-y-5" id="produk">
        <div class="flex items-end justify-between">
            <h2 class="text-2xl font-bold text-sky-800 dark:text-sky-300">Kategori produk</h2>
            <a href="{{ route('shop.products.index') }}" class="text-sm font-medium text-sky-600 hover:underline">Lihat semua produk &rarr;</a>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($this->categories as $item)
                <a wire:key="cat-{{ $item->id }}" href="{{ route('shop.products.index', ['kategori' => $item->slug]) }}" class="rounded-full border border-sky-200 bg-white px-4 py-1.5 text-sm font-medium text-sky-700 transition hover:bg-sky-100">{{ $item->name }}</a>
            @endforeach
        </div>

        @if ($this->products->isEmpty())
            <p class="rounded-2xl border border-dashed border-sky-200 bg-white p-8 text-center text-slate-500">Tiada produk lagi.</p>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->products as $product)
                    <x-shop.product-card :product="$product" wire:key="prod-{{ $product->id }}" />
                @endforeach
            </div>
        @endif
    </section>
</div>
