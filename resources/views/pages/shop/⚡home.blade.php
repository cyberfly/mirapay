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

<div class="space-y-20">
    @if ($this->featured)
        @php($hero = $this->featured)
        <section class="relative overflow-hidden rounded-3xl bg-linear-to-br from-sky-50 via-white to-indigo-50 ring-1 ring-slate-200/70">
            <div class="pointer-events-none absolute -end-24 -top-24 size-72 rounded-full bg-sky-200/40 blur-3xl"></div>
            <div class="relative grid items-center gap-8 md:grid-cols-2">
                <div class="space-y-6 p-8 md:p-12 lg:p-14">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-sky-700 shadow-sm ring-1 ring-sky-100">
                        <span class="size-1.5 rounded-full bg-sky-500"></span>
                        Produk utama
                    </span>
                    <h1 class="text-4xl font-bold leading-tight tracking-tight text-slate-900 md:text-5xl">{{ $hero->name }}</h1>
                    @if ($hero->tagline)
                        <p class="max-w-md text-lg leading-relaxed text-slate-600">{{ $hero->tagline }}</p>
                    @endif
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl font-bold text-slate-900">{{ Money::format($hero->price) }}</span>
                        @if ($hero->discountPercent())
                            <span class="text-lg text-slate-400 line-through">{{ Money::format($hero->compare_at_price) }}</span>
                            <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-sm font-semibold text-rose-600">Jimat {{ $hero->discountPercent() }}%</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <a href="{{ route('shop.products.show', $hero) }}" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-6 py-3 font-semibold text-white shadow-sm shadow-sky-600/20 transition hover:bg-sky-700">
                            Beli sekarang
                            <flux:icon.arrow-right variant="micro" />
                        </a>
                        <a href="{{ route('shop.products.index') }}" class="inline-flex items-center rounded-xl px-5 py-3 font-semibold text-slate-700 transition hover:bg-white">Lihat semua produk</a>
                    </div>
                </div>
                <div class="px-8 pb-8 md:p-10 md:ps-0">
                    <div class="aspect-4/3 overflow-hidden rounded-2xl bg-white shadow-xl shadow-slate-900/5 ring-1 ring-slate-200/70">
                        @if ($hero->imageUrl())
                            <img src="{{ $hero->imageUrl() }}" alt="{{ $hero->name }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center bg-linear-to-br from-sky-50 to-slate-100">
                                <x-app-logo-icon class="size-16 text-sky-200" />
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="grid gap-4 sm:grid-cols-3">
        <div class="flex items-start gap-4 rounded-2xl bg-slate-50 p-5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm ring-1 ring-slate-200/70"><flux:icon.bolt variant="mini" /></span>
            <div>
                <p class="font-semibold text-slate-900">Beli terus</p>
                <p class="text-sm text-slate-500">Tiada troli. Checkout terus dari halaman produk.</p>
            </div>
        </div>
        <div class="flex items-start gap-4 rounded-2xl bg-slate-50 p-5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm ring-1 ring-slate-200/70"><flux:icon.shield-check variant="mini" /></span>
            <div>
                <p class="font-semibold text-slate-900">Pembayaran selamat</p>
                <p class="text-sm text-slate-500">Setiap bayaran disahkan oleh pasukan kami.</p>
            </div>
        </div>
        <div class="flex items-start gap-4 rounded-2xl bg-slate-50 p-5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm ring-1 ring-slate-200/70"><flux:icon.clipboard-document-check variant="mini" /></span>
            <div>
                <p class="font-semibold text-slate-900">Jejak pesanan</p>
                <p class="text-sm text-slate-500">Semak status pesanan anda bila-bila masa.</p>
            </div>
        </div>
    </section>

    @if ($this->promotions->isNotEmpty())
        <section class="space-y-6">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-rose-500">Promosi</p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">Tawaran istimewa untuk anda</h2>
                </div>
            </div>
            <div class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->promotions as $product)
                    <x-shop.product-card :product="$product" wire:key="promo-{{ $product->id }}" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="space-y-6" id="produk">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-sky-600">Koleksi</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">Kategori produk</h2>
            </div>
            <a href="{{ route('shop.products.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-sky-600 transition hover:text-sky-700">
                Lihat semua produk
                <flux:icon.arrow-right variant="micro" />
            </a>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($this->categories as $item)
                <a wire:key="cat-{{ $item->id }}" href="{{ route('shop.products.index', ['kategori' => $item->slug]) }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700">{{ $item->name }}</a>
            @endforeach
        </div>

        @if ($this->products->isEmpty())
            <p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center text-slate-500">Tiada produk lagi.</p>
        @else
            <div class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->products as $product)
                    <x-shop.product-card :product="$product" wire:key="prod-{{ $product->id }}" />
                @endforeach
            </div>
        @endif
    </section>
</div>
