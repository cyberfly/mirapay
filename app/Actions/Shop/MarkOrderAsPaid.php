<?php

namespace App\Actions\Shop;

use App\Enums\OrderStatus;
use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPaidMail;
use App\Models\Order;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Mail;

class MarkOrderAsPaid
{
    public function __invoke(Order $order, ?string $reference = null): Order
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

        $order->refresh();

        $this->notify($order);

        return $order;
    }

    protected function notify(Order $order): void
    {
        Mail::to($order->email)->queue(new OrderPaidMail($order));

        $admins = User::where('is_admin', true)->pluck('email');

        if ($admins->isNotEmpty()) {
            Mail::to($admins->all())->queue(new NewOrderAdminMail($order));
        }
    }
}
