<x-mail::message>
# Hai {{ $subscription->customer_name }},

@if ($expired)
Langganan **{{ $subscription->product_name }}** anda telah tamat kerana bayaran tidak diterima.
@else
Langganan **{{ $subscription->product_name }}** anda telah dibatalkan dan tidak akan diperbaharui lagi.

@if ($subscription->ends_at?->isFuture())
Anda masih boleh menggunakannya sehingga **{{ $subscription->ends_at->format('d/m/Y') }}**.
@endif
@endif

<x-mail::button :url="route('shop.products.index')">
Lihat Produk
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
