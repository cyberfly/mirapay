<?php

namespace App\Http\Controllers;

use App\Actions\Shop\CancelOrder;
use App\Actions\Shop\RecordGatewayPayment;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeObject;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, RecordGatewayPayment $recordPayment, CancelOrder $cancel): Response
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

        match ($event->type) {
            'checkout.session.completed' => ($session->payment_status ?? null) === 'paid'
                ? $this->paymentReceived($session, $recordPayment)
                : null,
            'checkout.session.async_payment_succeeded' => $this->paymentReceived($session, $recordPayment),
            'checkout.session.expired' => $this->sessionExpired($session, $cancel),
            default => null,
        };

        return response('OK');
    }

    protected function paymentReceived(StripeObject $session, RecordGatewayPayment $recordPayment): void
    {
        $order = $this->orderForPayment($session);

        // A payment on an older session leaves the current one open: close it so it cannot be paid too.
        $recordPayment(
            $order,
            $session->payment_intent ?? null,
            expireGatewaySession: $order !== null && $order->stripe_session_id !== $session->id,
            gateway: 'Stripe',
            context: ['session' => $session->id, 'payment_intent' => $session->payment_intent ?? null],
        );
    }

    protected function sessionExpired(StripeObject $session, CancelOrder $cancel): void
    {
        // Only the order's current session may cancel it; an older, superseded one must not.
        $order = Order::where('stripe_session_id', $session->id)->first();

        if ($order?->status === OrderStatus::Pending) {
            $cancel($order, expireGatewaySession: false);
        }
    }

    protected function orderForPayment(StripeObject $session): ?Order
    {
        $order = Order::where('stripe_session_id', $session->id)->first();

        if ($order === null && filled($session->client_reference_id ?? null)) {
            $order = Order::where('public_id', $session->client_reference_id)->first();
        }

        return $order;
    }
}
