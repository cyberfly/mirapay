<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class StripePaymentGateway implements PaymentGateway
{
    public function __construct(protected StripeClient $client) {}

    public function checkoutUrl(Order $order): string
    {
        // Never leave two payable sessions behind for one order: a payment made on a superseded
        // session would otherwise not be attributable to the order.
        if ($order->stripe_session_id !== null) {
            $existing = $this->client->checkout->sessions->retrieve($order->stripe_session_id);

            if ($existing->status === 'open' && filled($existing->url)) {
                return $existing->url;
            }

            // Already paid; the webhook has yet to catch up. Do not offer a second payment.
            if ($existing->status === 'complete') {
                return route('shop.orders.show', $order);
            }
        }

        $name = $order->quantity > 1
            ? "{$order->product_name} × {$order->quantity}"
            : $order->product_name;

        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => $order->public_id,
            'customer_email' => $order->email,
            'payment_method_types' => ['card', 'fpx'],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => config('services.stripe.currency'),
                    'unit_amount' => $order->total,
                    'product_data' => ['name' => $name, 'description' => $order->order_number],
                ],
            ]],
            'metadata' => ['order_number' => $order->order_number],
            'expires_at' => now()->addMinutes(30)->getTimestamp(),
            'success_url' => route('shop.orders.show', $order),
            'cancel_url' => route('shop.orders.show', $order),
        ]);

        $order->forceFill([
            'payment_gateway' => 'stripe',
            'stripe_session_id' => $session->id,
        ])->save();

        return $session->url;
    }

    public function expire(Order $order): void
    {
        if ($order->stripe_session_id === null) {
            return;
        }

        try {
            $this->client->checkout->sessions->expire($order->stripe_session_id);
        } catch (InvalidRequestException) {
            // Session is no longer open (already expired or paid): nothing left to close.
        } catch (ApiErrorException $e) {
            Log::warning('Stripe: gagal meluputkan sesi checkout.', [
                'order' => $order->order_number,
                'session' => $order->stripe_session_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
