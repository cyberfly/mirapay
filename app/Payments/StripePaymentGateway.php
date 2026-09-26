<?php

namespace App\Payments;

use App\Models\Order;
use Stripe\StripeClient;

class StripePaymentGateway implements PaymentGateway
{
    public function checkoutUrl(Order $order): string
    {
        $client = new StripeClient(config('services.stripe.secret'));

        $name = $order->quantity > 1
            ? "{$order->product_name} × {$order->quantity}"
            : $order->product_name;

        $session = $client->checkout->sessions->create([
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
}
