<?php

namespace App\Actions\Shop;

use App\Enums\OrderStatus;
use App\Mail\PaymentNeedsAttentionMail;
use App\Models\Order;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * A gateway reports money captured for an order. It must always end up attributed to the order, or be flagged.
 */
class RecordGatewayPayment
{
    public function __construct(protected MarkOrderAsPaid $markAsPaid) {}

    /**
     * @param  bool  $expireGatewaySession  True when the payment was made on a superseded session, so the order's
     *                                      current one is still open and must be closed.
     * @param  array<string, mixed>  $context  Gateway identifiers for the log.
     */
    public function __invoke(?Order $order, ?string $reference, bool $expireGatewaySession, string $gateway, array $context = []): void
    {
        if ($order === null) {
            Log::warning("{$gateway}: bayaran diterima tetapi tiada pesanan sepadan.", $context);

            return;
        }

        if ($order->status === OrderStatus::Pending) {
            try {
                ($this->markAsPaid)($order, $reference, $expireGatewaySession);

                return;
            } catch (DomainException) {
                // Changed by a concurrent request; re-evaluate against the fresh state below.
                $order->refresh();
            }
        }

        // Redelivery of the payment that already marked this order paid.
        if ($order->status === OrderStatus::Paid && $reference !== null && $order->payment_reference === $reference) {
            return;
        }

        Log::critical("{$gateway}: bayaran diterima untuk pesanan yang tidak lagi menunggu bayaran.", [
            'order' => $order->order_number,
            'status' => $order->status->value,
            ...$context,
        ]);

        $admins = User::where('is_admin', true)->pluck('email');

        if ($admins->isNotEmpty()) {
            Mail::to($admins->all())->queue(new PaymentNeedsAttentionMail($order, $reference, $gateway));
        }
    }
}
