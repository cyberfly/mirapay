@props(['product'])

<a href="{{ route('shop.products.show', $product) }}" {{ $attributes->class('group flex flex-col') }}>
    <div class="relative aspect-4/3 overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200/70">
        @if ($product->imageUrl())
            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="flex size-full items-center justify-center bg-linear-to-br from-sky-50 to-slate-100">
                <x-app-logo-icon class="size-10 text-sky-200" />
            </div>
        @endif
        @if ($product->discountPercent())
            <span class="absolute start-3 top-3 rounded-full bg-rose-500 px-2.5 py-1 text-xs font-semibold text-white shadow-sm">-{{ $product->discountPercent() }}%</span>
        @endif
        @if ($product->isSoldOut())
            <span class="absolute end-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm backdrop-blur">Habis</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-1 px-1 pt-4">
        @if ($product->category)
            <span class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $product->category->name }}</span>
        @endif
        <h3 class="font-semibold text-slate-900 transition group-hover:text-sky-600">{{ $product->name }}</h3>
        <div class="mt-auto flex items-baseline gap-2 pt-1">
            <span class="font-semibold text-slate-900">{{ \App\Support\Money::format($product->price) }}</span>
            @if ($product->billing_interval)
                <span class="text-sm text-slate-500">/ {{ $product->billing_interval->unit() }}</span>
            @endif
            @if ($product->discountPercent())
                <span class="text-sm text-slate-400 line-through">{{ \App\Support\Money::format($product->compare_at_price) }}</span>
            @endif
        </div>
    </div>
</a>
