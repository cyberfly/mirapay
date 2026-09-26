<?php

namespace App\Payments;

use App\Models\Order;

class ManualPaymentGateway implements PaymentGateway
{
    public function checkoutUrl(Order $order): string
    {
        return route('shop.orders.show', $order);
    }

    public function expire(Order $order): void
    {
        //
    }
}
