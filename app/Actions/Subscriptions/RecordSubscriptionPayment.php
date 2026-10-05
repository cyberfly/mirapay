<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\Subscription;

class RecordSubscriptionPayment
{
    /**
     * Starts or extends the billing period a paid order covers.
     */
    public function __invoke(Order $order): void
    {
        if ($order->subscription_id === null) {
            return;
        }

        $subscription = Subscription::find($order->subscription_id);

        if ($subscription === null) {
            return;
        }

        // A renewal covers the period it was raised for, even when it is paid a few days late.
        $start = $order->is_renewal && $order->period_start !== null
            ? $order->period_start->startOfDay()
            : now();
        $end = $subscription->billing_interval->addTo($start->toImmutable());

        // A subscription cancelled, expired or paused in the meantime must not come back to life.
        Subscription::whereKey($subscription->id)
            ->whereIn('status', [SubscriptionStatus::Pending, SubscriptionStatus::PastDue, SubscriptionStatus::Active])
            ->update([
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $start,
                'current_period_end' => $end,
                'next_billing_date' => $end->toDateString(),
                'past_due_since' => null,
                'last_reminded_at' => null,
            ]);
    }
}
