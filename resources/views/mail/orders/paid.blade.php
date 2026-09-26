<x-mail::message>
# Terima kasih, {{ $order->customer_name }}!

Bayaran anda telah diterima. Pesanan **{{ $order->order_number }}** sedang diproses.

<x-mail::table>
| Produk | Kuantiti | Jumlah |
| :----- | :------: | -----: |
| {{ $order->product_name }} | {{ $order->quantity }} | {{ \App\Support\Money::format($order->subtotal) }} |
</x-mail::table>

@if ($order->discount > 0)
Diskaun: -{{ \App\Support\Money::format($order->discount) }}  
@endif
@if ($order->shipping_fee > 0)
Penghantaran: {{ \App\Support\Money::format($order->shipping_fee) }}  
@endif
**Jumlah dibayar: {{ \App\Support\Money::format($order->total) }}**

@if ($order->requires_shipping)
**Alamat penghantaran**  
{{ $order->address_line1 }}@if ($order->address_line2), {{ $order->address_line2 }}@endif  
{{ $order->postcode }} {{ $order->city }}, {{ $order->stateLabel() }}
@endif

<x-mail::button :url="route('shop.orders.show', $order)">
Lihat Pesanan
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
