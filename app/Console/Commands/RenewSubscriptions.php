<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\ExpireSubscription;
use App\Actions\Subscriptions\GenerateRenewalOrder;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionPaymentFailedMail;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class RenewSubscriptions extends Command
{
    protected $signature = 'subscriptions:renew';

    protected $description = 'Raise due renewal orders, remind unpaid ones and expire subscriptions past their grace period';

    public function handle(GenerateRenewalOrder $generate, ExpireSubscription $expire): int
    {
        $renewed = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereDate('next_billing_date', '<=', today())
            ->each(function (Subscription $subscription) use ($generate, &$renewed): void {
                if ($generate($subscription) !== null) {
                    $renewed++;
                }
            });

        $expired = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::PastDue)
            ->where('past_due_since', '<=', now()->subDays(config('subscriptions.grace_days')))
            ->each(function (Subscription $subscription) use ($expire, &$expired): void {
                $expire($subscription);
                $expired++;
            });

        $reminded = 0;
        $reminderCutoff = now()->subDays(config('subscriptions.reminder_every_days'));

        Subscription::query()
            ->where('status', SubscriptionStatus::PastDue)
            ->where(fn ($query) => $query
                ->where('last_reminded_at', '<=', $reminderCutoff)
                ->orWhere(fn ($query) => $query->whereNull('last_reminded_at')->where('past_due_since', '<=', $reminderCutoff)))
            ->each(function (Subscription $subscription) use (&$reminded): void {
                $order = $subscription->orders()
                    ->where('is_renewal', true)
                    ->where('status', OrderStatus::Pending)
                    ->latest('id')
                    ->first();

                if ($order === null) {
                    return;
                }

                Mail::to($subscription->email)->queue(new SubscriptionPaymentFailedMail($subscription, $order));
                $subscription->forceFill(['last_reminded_at' => now()])->save();
                $reminded++;
            });

        $this->info("Diperbaharui: {$renewed}, peringatan: {$reminded}, tamat: {$expired}.");

        return self::SUCCESS;
    }
}
