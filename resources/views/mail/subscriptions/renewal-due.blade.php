<x-mail::message>
# Hai {{ $order->customer_name }},

Langganan **{{ $order->product_name }}** anda perlu diperbaharui. Sila jelaskan bayaran **{{ \App\Support\Money::format($order->total) }}** untuk meneruskan langganan.

<x-mail::button :url="route('shop.orders.show', $order)">
Bayar Sekarang
</x-mail::button>

Jika bayaran tidak diterima dalam {{ config('subscriptions.grace_days') }} hari, langganan anda akan tamat.

Urus langganan anda [di sini]({{ route('shop.subscriptions.show', $order->subscription) }}).

{{ config('app.name') }}
</x-mail::message>
