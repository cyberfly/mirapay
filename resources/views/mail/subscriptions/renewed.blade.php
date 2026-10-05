<x-mail::message>
# Terima kasih, {{ $order->customer_name }}!

Langganan **{{ $order->product_name }}** anda telah diperbaharui. Bayaran **{{ \App\Support\Money::format($order->total) }}** untuk pesanan **{{ $order->order_number }}** telah diterima.

@if ($order->subscription?->current_period_end)
Langganan anda aktif sehingga **{{ $order->subscription->current_period_end->format('d/m/Y') }}**.
@endif

<x-mail::button :url="route('shop.subscriptions.show', $order->subscription)">
Urus Langganan
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
