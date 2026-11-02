<?php

namespace App\Http\Controllers;

use App\Actions\Shop\RecordGatewayPayment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives CHIP purchase callbacks (success_callback / purchase.paid webhook).
 * The body is the purchase object, signed with the company key in the X-Signature header.
 */
class ChipWebhookController extends Controller
{
    public function __invoke(Request $request, RecordGatewayPayment $recordPayment): Response
    {
        $payload = $request->getContent();

        if (! $this->hasValidSignature($payload, (string) $request->header('X-Signature'))) {
            return response('Invalid signature', 400);
        }

        $purchase = json_decode($payload, true);

        if (! is_array($purchase) || blank($purchase['id'] ?? null)) {
            return response('Invalid payload', 400);
        }

        if (($purchase['status'] ?? null) === 'paid') {
            $this->paymentReceived($purchase, $recordPayment);
        }

        return response('OK');
    }

    /**
     * @param  array<string, mixed>  $purchase
     */
    protected function paymentReceived(array $purchase, RecordGatewayPayment $recordPayment): void
    {
        $reference = (string) $purchase['id'];
        $order = $this->orderForPayment($purchase);

        // A payment on an older purchase leaves the current one open: cancel it so it cannot be paid too.
        $recordPayment(
            $order,
            $reference,
            expireGatewaySession: $order !== null && $order->payment_reference !== $reference,
            gateway: 'CHIP',
            context: ['purchase' => $reference, 'reference' => $purchase['reference'] ?? null],
        );
    }

    /**
     * @param  array<string, mixed>  $purchase
     */
    protected function orderForPayment(array $purchase): ?Order
    {
        $order = Order::where('payment_gateway', 'chip')->where('payment_reference', $purchase['id'])->first();

        if ($order === null && filled($purchase['reference'] ?? null)) {
            $order = Order::where('order_number', $purchase['reference'])->first();
        }

        return $order;
    }

    protected function hasValidSignature(string $payload, string $signature): bool
    {
        $publicKey = str_replace('\n', "\n", (string) config('services.chip.public_key'));
        $decoded = base64_decode($signature, true);

        if ($publicKey === '' || $decoded === false || $decoded === '') {
            return false;
        }

        $key = openssl_pkey_get_public($publicKey);

        if ($key === false) {
            Log::error('CHIP: CHIP_PUBLIC_KEY tidak sah.');

            return false;
        }

        return openssl_verify($payload, $decoded, $key, OPENSSL_ALGO_SHA256) === 1;
    }
}
