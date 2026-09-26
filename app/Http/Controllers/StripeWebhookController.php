<?php

namespace App\Http\Controllers;

use App\Actions\Shop\CancelOrder;
use App\Actions\Shop\MarkOrderAsPaid;
use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, MarkOrderAsPaid $markAsPaid, CancelOrder $cancel): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response('Invalid payload', 400);
        }

        $session = $event->data->object;

        $order = match ($event->type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.expired' => Order::where('stripe_session_id', $session->id)->first(),
            default => null,
        };

        if ($order === null || $order->status !== OrderStatus::Pending) {
            return response('OK');
        }

        try {
            match ($event->type) {
                'checkout.session.completed' => $session->payment_status === 'paid'
                    ? $markAsPaid($order, $session->payment_intent)
                    : null,
                'checkout.session.async_payment_succeeded' => $markAsPaid($order, $session->payment_intent),
                'checkout.session.expired' => $cancel($order),
            };
        } catch (DomainException) {
            // Already handled by a concurrent request.
        }

        return response('OK');
    }
}
