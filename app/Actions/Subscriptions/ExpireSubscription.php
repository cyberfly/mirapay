<?php

namespace App\Actions\Subscriptions;

use App\Actions\Shop\CancelOrder;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionCancelledMail;
use App\Models\Subscription;
use Illuminate\Support\Facades\Mail;

class ExpireSubscription
{
    public function __construct(protected CancelOrder $cancelOrder) {}

    /**
     * Ends a subscription whose renewal stayed unpaid past the grace period.
     */
    public function __invoke(Subscription $subscription): void
    {
        $updated = Subscription::whereKey($subscription->id)
            ->where('status', SubscriptionStatus::PastDue)
            ->update(['status' => SubscriptionStatus::Expired, 'ends_at' => now(), 'next_billing_date' => null]);

        if ($updated === 0) {
            return;
        }

        $subscription->refresh();

        $subscription->orders()->where('status', OrderStatus::Pending)->get()
            ->each(fn ($order) => ($this->cancelOrder)($order));

        Mail::to($subscription->email)->queue(new SubscriptionCancelledMail($subscription, expired: true));
    }
}
