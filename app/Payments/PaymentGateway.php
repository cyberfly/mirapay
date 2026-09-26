<?php

namespace App\Payments;

use App\Models\Order;

interface PaymentGateway
{
    /**
     * URL the customer is sent to after the order is placed.
     */
    public function checkoutUrl(Order $order): string;

    /**
     * Close any payment session still open for the order, so it can no longer be paid.
     * Best effort: a session that is already closed is not an error.
     */
    public function expire(Order $order): void;
}
