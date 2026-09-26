<?php

namespace App\Payments;

use App\Models\Order;

interface PaymentGateway
{
    /**
     * URL the customer is sent to after the order is placed.
     */
    public function checkoutUrl(Order $order): string;
}
