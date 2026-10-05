<?php

namespace App\Actions\Shop;

use App\Actions\Subscriptions\RecordSubscriptionPayment;
use App\Enums\OrderStatus;
use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPaidMail;
use App\Mail\SubscriptionRenewedMail;
use App\Models\Order;
use App\Models\User;
use App\Payments\PaymentGateway;
use DomainException;
use Illuminate\Support\Facades\Mail;

class MarkOrderAsPaid
{
    public function __construct(protected PaymentGateway $gateway) {}

    /**
     * @param  bool  $expireGatewaySession  Close the open payment session too, so the customer cannot pay a second time
     *                                      after an admin marked the order paid by hand. Pass false when the gateway has
     *                                      just reported the payment on the session that is still current.
     */
    public function __invoke(Order $order, ?string $reference = null, bool $expireGatewaySession = true): Order
    {
        $updated = Order::whereKey($order->id)
            ->where('status', OrderStatus::Pending)
            ->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'payment_reference' => $reference,
            ]);

        if ($updated === 0) {
            throw new DomainException('Hanya pesanan yang belum dibayar boleh ditanda sebagai dibayar.');
        }

        if ($expireGatewaySession) {
            $this->gateway->expire($order);
        }

        $order->refresh();

        app(RecordSubscriptionPayment::class)($order);

        $this->notify($order);

        return $order;
    }

    protected function notify(Order $order): void
    {
        Mail::to($order->email)->queue($order->is_renewal ? new SubscriptionRenewedMail($order) : new OrderPaidMail($order));

        $admins = User::where('is_admin', true)->pluck('email');

        if ($admins->isNotEmpty()) {
            Mail::to($admins->all())->queue(new NewOrderAdminMail($order));
        }
    }
}
