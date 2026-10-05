<?php

namespace App\Actions\Subscriptions;

use App\Actions\Shop\CancelOrder;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionCancelledMail;
use App\Models\Subscription;
use DomainException;
use Illuminate\Support\Facades\Mail;

class CancelSubscription
{
    public function __construct(protected CancelOrder $cancelOrder) {}

    /**
     * Stops future renewals. A customer who has already paid keeps access until the period ends.
     */
    public function __invoke(Subscription $subscription): Subscription
    {
        $wasPending = $subscription->status === SubscriptionStatus::Pending;

        $updated = Subscription::whereKey($subscription->id)
            ->whereIn('status', [
                SubscriptionStatus::Pending, SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Paused,
            ])
            ->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
                'ends_at' => $wasPending ? now() : ($subscription->current_period_end ?? now()),
                'next_billing_date' => null,
            ]);

        if ($updated === 0) {
            throw new DomainException('Langganan ini tidak boleh dibatalkan.');
        }

        $subscription->refresh();

        // An unpaid first order or renewal can no longer be paid.
        $subscription->orders()->where('status', OrderStatus::Pending)->get()
            ->each(fn ($order) => ($this->cancelOrder)($order));

        if (! $wasPending) {
            Mail::to($subscription->email)->queue(new SubscriptionCancelledMail($subscription));
        }

        return $subscription;
    }
}
