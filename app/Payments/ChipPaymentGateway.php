<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChipPaymentGateway implements PaymentGateway
{
    /** Purchase statuses on which the customer can still pay. */
    protected const PAYABLE = ['created', 'sent', 'viewed'];

    public function checkoutUrl(Order $order): string
    {
        // Never leave two payable purchases behind for one order: a payment made on a superseded
        // purchase would otherwise not be attributable to the order.
        if ($order->payment_gateway === 'chip' && $order->payment_reference !== null) {
            $existing = $this->http()->get("purchases/{$order->payment_reference}/")->throw()->json();

            if (in_array($existing['status'] ?? null, self::PAYABLE, true) && filled($existing['checkout_url'] ?? null)) {
                return $existing['checkout_url'];
            }

            // Already paid; the webhook has yet to catch up. Do not offer a second payment.
            if (($existing['status'] ?? null) === 'paid') {
                return route('shop.orders.show', $order);
            }
        }

        $name = $order->quantity > 1
            ? "{$order->product_name} × {$order->quantity}"
            : $order->product_name;

        $purchase = $this->http()->post('purchases/', [
            'brand_id' => config('services.chip.brand_id'),
            'reference' => $order->public_id,
            'client' => [
                'email' => $order->email,
                'full_name' => $order->customer_name,
                'phone' => $order->phone,
            ],
            'purchase' => [
                'currency' => config('services.chip.currency'),
                'products' => [[
                    'name' => $name,
                    'quantity' => 1,
                    'price' => $order->total,
                ]],
            ],
            'success_redirect' => route('shop.orders.show', $order),
            'failure_redirect' => route('shop.orders.show', $order),
            'success_callback' => route('chip.webhook'),
            'send_receipt' => false,
        ])->throw()->json();

        $order->forceFill([
            'payment_gateway' => 'chip',
            'payment_reference' => $purchase['id'],
        ])->save();

        return $purchase['checkout_url'];
    }

    public function expire(Order $order): void
    {
        if ($order->payment_gateway !== 'chip' || $order->payment_reference === null) {
            return;
        }

        try {
            $this->http()->post("purchases/{$order->payment_reference}/cancel/")->throw();
        } catch (RequestException $e) {
            // A purchase that is already paid, cancelled or expired cannot be cancelled again: nothing left to close.
            if ($e->response->clientError()) {
                return;
            }

            Log::warning('CHIP: gagal membatalkan purchase.', [
                'order' => $order->order_number,
                'purchase' => $order->payment_reference,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.chip.base_url'))
            ->withToken((string) config('services.chip.api_key'))
            ->acceptJson()
            ->asJson();
    }
}
