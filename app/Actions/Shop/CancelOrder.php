<?php

namespace App\Actions\Shop;

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CancelOrder
{
    public function __invoke(Order $order): Order
    {
        DB::transaction(function () use ($order): void {
            $updated = Order::whereKey($order->id)
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Paid])
                ->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

            if ($updated === 0) {
                return;
            }

            Product::whereKey($order->product_id)
                ->whereNotNull('stock')
                ->increment('stock', $order->quantity);

            if ($order->coupon_id !== null) {
                Coupon::whereKey($order->coupon_id)
                    ->where('used_count', '>', 0)
                    ->decrement('used_count');
            }
        });

        return $order->refresh();
    }
}
