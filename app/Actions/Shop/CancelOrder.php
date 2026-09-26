<?php

namespace App\Actions\Shop;

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

class CancelOrder
{
    public function __construct(protected PaymentGateway $gateway) {}

    /**
     * @param  bool  $expireGatewaySession  Close the open payment session too, so a cancelled order cannot still be paid.
     *                                      The gateway's own "expired" callback passes false: the session is already closed.
     */
    public function __invoke(Order $order, bool $expireGatewaySession = true): Order
    {
        $wasPending = $order->status === OrderStatus::Pending;

        $cancelled = DB::transaction(function () use ($order): bool {
            $updated = Order::whereKey($order->id)
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Paid])
                ->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

            if ($updated === 0) {
                return false;
            }

            Product::whereKey($order->product_id)
                ->whereNotNull('stock')
                ->increment('stock', $order->quantity);

            if ($order->coupon_id !== null) {
                Coupon::whereKey($order->coupon_id)
                    ->where('used_count', '>', 0)
                    ->decrement('used_count');
            }

            return true;
        });

        if ($cancelled && $wasPending && $expireGatewaySession) {
            $this->gateway->expire($order);
        }

        return $order->refresh();
    }
}
