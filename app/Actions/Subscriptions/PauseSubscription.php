<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use DomainException;

class PauseSubscription
{
    /**
     * No renewals are raised while paused; access lasts until the period already paid for ends.
     */
    public function __invoke(Subscription $subscription): Subscription
    {
        $updated = Subscription::whereKey($subscription->id)
            ->where('status', SubscriptionStatus::Active)
            ->update(['status' => SubscriptionStatus::Paused, 'paused_at' => now()]);

        if ($updated === 0) {
            throw new DomainException('Hanya langganan yang aktif boleh dijeda.');
        }

        return $subscription->refresh();
    }
}
