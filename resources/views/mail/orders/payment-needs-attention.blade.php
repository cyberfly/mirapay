<x-mail::message>
# Bayaran perlu disemak

{{ $gateway }} menerima bayaran untuk pesanan **{{ $order->order_number }}**, tetapi pesanan itu berstatus **{{ $order->status->label() }}** dan bayaran ini **tidak direkodkan** padanya.

Sila semak di {{ $gateway }} dan buat bayaran balik (refund) atau pulihkan pesanan jika perlu.

- Pelanggan: {{ $order->customer_name }} ({{ $order->email }})
- Jumlah pesanan: {{ \App\Support\Money::format($order->total) }}
- Rujukan {{ $gateway }}: {{ $paymentReference ?? '-' }}

<x-mail::button :url="route('admin.orders.show', $order)">
Buka di Admin
</x-mail::button>
</x-mail::message>
