<x-mail::message>
# Pesanan baru dibayar

**{{ $order->order_number }}** — {{ \App\Support\Money::format($order->total) }}

- Produk: {{ $order->product_name }} × {{ $order->quantity }}
- Pelanggan: {{ $order->customer_name }}
- Emel: {{ $order->email }}
- Telefon: {{ $order->phone }}
@if ($order->coupon_code)
- Kupon: {{ $order->coupon_code }}
@endif

@if ($order->requires_shipping)
**Alamat penghantaran**  
{{ $order->address_line1 }}@if ($order->address_line2), {{ $order->address_line2 }}@endif  
{{ $order->postcode }} {{ $order->city }}, {{ $order->stateLabel() }}
@endif

<x-mail::button :url="route('admin.orders.show', $order)">
Buka di Admin
</x-mail::button>
</x-mail::message>
