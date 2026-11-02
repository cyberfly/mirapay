<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CHIP Collect (https://docs.chip-in.asia). The purchase id is kept in the order's payment_reference:
 * one CHIP purchase is one payment, so the same id identifies the payment once it is paid.
 */
class ChipPaymentGateway implements PaymentGateway
{
    /** The customer can still pay on this purchase's checkout page. */
    public const PAYABLE_STATUSES = ['created', 'sent', 'viewed'];

    /** No new payment can be made on the purchase, but it has not been paid (yet). */
    public const CLOSED_STATUSES = ['cancelled', 'expired', 'overdue', 'error', 'blocked'];

    public function checkoutUrl(Order $order): string
    {
        // Never leave two payable purchases behind for one order: a payment on a superseded
        // purchase would otherwise be harder to attribute to the order.
        $returnUrl = route('shop.orders.show', $order);

        if ($order->payment_gateway === 'chip' && filled($order->payment_reference)) {
            $existing = $this->client()->get("purchases/{$order->payment_reference}/")->throw()->json();
            $status = $existing['status'] ?? null;

            if (in_array($status, self::PAYABLE_STATUSES, true) && filled($existing['checkout_url'] ?? null)) {
                return $existing['checkout_url'];
            }

            // Paid or a payment is in flight; the callback has yet to catch up. Do not offer a second payment.
            if (! in_array($status, self::CLOSED_STATUSES, true)) {
                return $returnUrl;
            }
        }

        $purchase = $this->client()->post('purchases/', [
            'brand_id' => config('services.chip.brand_id'),
            'reference' => $order->order_number,
            'client' => [
                'email' => $order->email,
                'full_name' => $order->customer_name,
                'phone' => $order->phone,
            ],
            'purchase' => [
                'currency' => config('services.chip.currency'),
                'products' => [[
                    'name' => $order->lineItemName(),
                    'quantity' => 1,
                    'price' => $order->total,
                ]],
            ],
            'due' => now()->addMinutes(30)->getTimestamp(),
            'due_strict' => true,
            'send_receipt' => false,
            'success_redirect' => $returnUrl,
            'failure_redirect' => $returnUrl,
            'cancel_redirect' => $returnUrl,
            'success_callback' => route('chip.webhook'),
        ])->throw()->json();

        $order->forceFill([
            'payment_gateway' => 'chip',
            'payment_reference' => $purchase['id'],
        ])->save();

        return $purchase['checkout_url'];
    }

    public function expire(Order $order): void
    {
        if ($order->payment_gateway !== 'chip' || blank($order->payment_reference)) {
            return;
        }

        try {
            $response = $this->client()->post("purchases/{$order->payment_reference}/cancel/");
        } catch (ConnectionException $e) {
            $this->logCancelFailure($order, $e->getMessage());

            return;
        }

        // 400/404: the purchase can no longer be cancelled (already paid, expired or cancelled): nothing left to close.
        if ($response->successful() || in_array($response->status(), [400, 404], true)) {
            return;
        }

        $this->logCancelFailure($order, $response->body());
    }

    protected function logCancelFailure(Order $order, string $error): void
    {
        Log::warning('CHIP: gagal membatalkan purchase.', [
            'order' => $order->order_number,
            'purchase' => $order->payment_reference,
            'error' => $error,
        ]);
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.chip.url'), '/').'/')
            ->withToken((string) config('services.chip.secret'))
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
