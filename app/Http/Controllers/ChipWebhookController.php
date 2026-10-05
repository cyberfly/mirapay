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

class ChipWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MarkOrderAsPaid $markAsPaid,
        CancelOrder $cancel,
        FlagUnattributedPayment $flag,
    ): Response {
        if (! $this->validSignature($request)) {
            return response('Invalid signature', 400);
        }

        $purchase = $request->json()->all();

        match ($purchase['event_type'] ?? null) {
            'purchase.paid' => $this->paymentReceived($purchase, $markAsPaid, $flag),
            'purchase.cancelled', 'purchase.expired' => $this->purchaseClosed($purchase, $cancel),
            default => null,
        };

        return response('OK');
    }

    protected function validSignature(Request $request): bool
    {
        $signature = base64_decode((string) $request->header('X-Signature'), true);
        $publicKey = openssl_pkey_get_public((string) config('services.chip.public_key'));

        if ($signature === false || $signature === '' || $publicKey === false) {
            return false;
        }

        return openssl_verify($request->getContent(), $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Money has been captured. It must always end up attributed to an order, or be flagged.
     *
     * @param  array<string, mixed>  $purchase
     */
    protected function paymentReceived(array $purchase, MarkOrderAsPaid $markAsPaid, FlagUnattributedPayment $flag): void
    {
        $reference = $purchase['id'] ?? null;
        $order = $this->orderForPurchase($purchase);

        if ($order === null) {
            Log::warning('CHIP: bayaran diterima tetapi tiada pesanan sepadan.', ['purchase' => $reference]);

            return;
        }

        if ($order->status === OrderStatus::Pending) {
            try {
                // A payment on an older purchase leaves the current one open: close it so it cannot be paid too.
                $markAsPaid($order, $reference, expireGatewaySession: $order->payment_reference !== $reference);

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

        $flag('CHIP', $order, $reference, ['purchase' => $reference]);
    }

    /**
     * @param  array<string, mixed>  $purchase
     */
    protected function purchaseClosed(array $purchase, CancelOrder $cancel): void
    {
        // Only the order's current purchase may cancel it; an older, superseded one must not.
        $order = Order::where('payment_gateway', 'chip')->where('payment_reference', $purchase['id'] ?? null)->first();

        if ($order?->status === OrderStatus::Pending) {
            $cancel($order, expireGatewaySession: false);
        }
    }

    /**
     * @param  array<string, mixed>  $purchase
     */
    protected function orderForPurchase(array $purchase): ?Order
    {
        $order = Order::where('payment_gateway', 'chip')->where('payment_reference', $purchase['id'] ?? null)->first();

        if ($order === null && filled($purchase['reference'] ?? null)) {
            $order = Order::where('public_id', $purchase['reference'])->first();
        }

        return $order;
    }
}
