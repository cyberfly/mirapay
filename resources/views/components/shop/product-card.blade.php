@props(['product'])

<a href="{{ route('shop.products.show', $product) }}" class="group flex flex-col overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
    <div class="relative aspect-4/3 bg-linear-to-br from-sky-100 to-sky-200 dark:from-sky-950 dark:to-slate-800">
        @if ($product->imageUrl())
            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="size-full object-cover">
        @endif
        @if ($product->discountPercent())
            <span class="absolute start-3 top-3 rounded-full bg-sky-600 px-2.5 py-1 text-xs font-semibold text-white">-{{ $product->discountPercent() }}%</span>
        @endif
        @if ($product->isSoldOut())
            <span class="absolute end-3 top-3 rounded-full bg-slate-800 px-2.5 py-1 text-xs font-semibold text-white">Habis</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-1 p-4">
        @if ($product->category)
            <span class="text-xs font-medium text-sky-600">{{ $product->category->name }}</span>
        @endif
        <h3 class="font-semibold group-hover:text-sky-600">{{ $product->name }}</h3>
        <div class="mt-auto flex items-baseline gap-2 pt-2">
            <span class="text-lg font-bold text-sky-700 dark:text-sky-400">{{ \App\Support\Money::format($product->price) }}</span>
            @if ($product->discountPercent())
                <span class="text-sm text-slate-400 line-through">{{ \App\Support\Money::format($product->compare_at_price) }}</span>
            @endif
        </div>
    </div>
</a>
