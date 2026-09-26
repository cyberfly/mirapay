<?php

namespace App\Actions\Shop;

use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;

class MarkOrderAsShipped
{
    public function __invoke(Order $order): Order
    {
        $updated = Order::whereKey($order->id)
            ->where('status', OrderStatus::Paid)
            ->where('requires_shipping', true)
            ->update([
                'status' => OrderStatus::Shipped,
                'shipped_at' => now(),
            ]);

        if ($updated === 0) {
            throw new DomainException('Hanya pesanan fizikal yang telah dibayar boleh ditanda sebagai dihantar.');
        }

        return $order->refresh();
    }
}
