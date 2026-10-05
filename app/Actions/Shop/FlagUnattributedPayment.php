<?php

namespace App\Actions\Shop;

use App\Mail\PaymentNeedsAttentionMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FlagUnattributedPayment
{
    /**
     * Money was captured for an order that can no longer take it: log it and tell the admins.
     *
     * @param  array<string, mixed>  $context  Gateway-specific identifiers for the log entry.
     */
    public function __invoke(string $gateway, Order $order, ?string $reference, array $context = []): void
    {
        Log::critical("{$gateway}: bayaran diterima untuk pesanan yang tidak lagi menunggu bayaran.", [
            'order' => $order->order_number,
            'status' => $order->status->value,
        ] + $context);

        $admins = User::where('is_admin', true)->pluck('email');

        if ($admins->isNotEmpty()) {
            Mail::to($admins->all())->queue(new PaymentNeedsAttentionMail($order, $reference));
        }
    }
}
