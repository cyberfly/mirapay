<?php

namespace App\Actions\Shop;

use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;

class MarkOrderAsPaid
{
    public function __invoke(Order $order, ?string $reference = null): Order
    {
        $updated = Order::whereKey($order->id)
            ->where('status', OrderStatus::Pending)
            ->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'payment_reference' => $reference,
            ]);

        if ($updated === 0) {
            throw new DomainException('Hanya pesanan yang belum dibayar boleh ditanda sebagai dibayar.');
        }

        return $order->refresh();
    }
}
