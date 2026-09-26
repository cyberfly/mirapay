@props(['product'])

@if ($product->category)
    <p class="text-sm font-semibold uppercase tracking-wider text-sky-600">{{ $product->category->name }}</p>
@endif
<h1 class="text-3xl font-bold leading-tight tracking-tight text-slate-900 md:text-4xl">{{ $product->name }}</h1>
@if ($product->tagline)
    <p class="text-lg leading-relaxed text-slate-600">{{ $product->tagline }}</p>
@endif
<div class="flex flex-wrap items-baseline gap-3 pt-1">
    <span class="text-3xl font-bold text-slate-900">{{ \App\Support\Money::format($product->price) }}</span>
    @if ($product->compare_at_price && $product->compare_at_price > $product->price)
        <span class="text-lg text-slate-400 line-through">{{ \App\Support\Money::format($product->compare_at_price) }}</span>
        @if ($product->discountPercent())
            <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-sm font-semibold text-rose-600">Jimat {{ $product->discountPercent() }}%</span>
        @endif
    @endif
</div>
