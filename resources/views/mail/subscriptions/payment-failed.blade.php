<x-mail::message>
# Hai {{ $subscription->customer_name }},

Kami belum menerima bayaran **{{ \App\Support\Money::format($order->total) }}** untuk langganan **{{ $subscription->product_name }}**.

@if ($subscription->past_due_since)
Sila jelaskan bayaran sebelum **{{ $subscription->past_due_since->addDays(config('subscriptions.grace_days'))->format('d/m/Y') }}** supaya langganan anda tidak tamat.
@endif

<x-mail::button :url="route('shop.orders.show', $order)">
Bayar Sekarang
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
