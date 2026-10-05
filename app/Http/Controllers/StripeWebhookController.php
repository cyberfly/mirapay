<?php

namespace App\Http\Controllers;

use App\Actions\Shop\CancelOrder;
use App\Actions\Shop\FlagUnattributedPayment;
use App\Actions\Shop\MarkOrderAsPaid;
use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeObject;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, MarkOrderAsPaid $markAsPaid, CancelOrder $cancel, FlagUnattributedPayment $flag): Response
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
                ? $this->paymentReceived($session, $markAsPaid, $flag)
                : null,
            'checkout.session.async_payment_succeeded' => $this->paymentReceived($session, $markAsPaid, $flag),
            'checkout.session.expired' => $this->sessionExpired($session, $cancel),
            default => null,
        };

        return response('OK');
    }

    /**
     * Money has been captured. It must always end up attributed to an order, or be flagged.
     */
    protected function paymentReceived(StripeObject $session, MarkOrderAsPaid $markAsPaid, FlagUnattributedPayment $flag): void
    {
        $reference = $session->payment_intent ?? null;
        $order = $this->orderForPayment($session);

        if ($order === null) {
            Log::warning('Stripe: bayaran diterima tetapi tiada pesanan sepadan.', [
                'session' => $session->id,
                'payment_intent' => $reference,
            ]);

            return;
        }

        if ($order->status === OrderStatus::Pending) {
            try {
                // A payment on an older session leaves the current one open: close it so it cannot be paid too.
                $markAsPaid($order, $reference, expireGatewaySession: $order->stripe_session_id !== $session->id);

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

        $flag('Stripe', $order, $reference, ['session' => $session->id, 'payment_intent' => $reference]);
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
