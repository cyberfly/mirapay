<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use DomainException;

class ResumeSubscription
{
    /**
     * Billing carries on from the end of the paid period; if that has already passed, the next renewal is due today.
     */
    public function __invoke(Subscription $subscription): Subscription
    {
        $periodEnd = $subscription->current_period_end?->startOfDay();
        $next = $periodEnd === null || $periodEnd->lessThan(today()) ? today() : $periodEnd;

        $updated = Subscription::whereKey($subscription->id)
            ->where('status', SubscriptionStatus::Paused)
            ->update([
                'status' => SubscriptionStatus::Active,
                'paused_at' => null,
                'next_billing_date' => $next->toDateString(),
            ]);

        if ($updated === 0) {
            throw new DomainException('Hanya langganan yang dijeda boleh disambung semula.');
        }

        return $subscription->refresh();
    }
}
