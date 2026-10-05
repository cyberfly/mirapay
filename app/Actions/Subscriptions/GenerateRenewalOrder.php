<?php

namespace App\Actions\Subscriptions;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionRenewalDueMail;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GenerateRenewalOrder
{
    /**
     * Raises the renewal order for a subscription that is due and emails the customer a payment link.
     * Returns null when nothing is due, so running it twice never bills a period twice.
     */
    public function __invoke(Subscription $subscription): ?Order
    {
        $order = DB::transaction(function () use ($subscription): ?Order {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);

            if ($subscription->status !== SubscriptionStatus::Active
                || $subscription->next_billing_date === null
                || $subscription->next_billing_date->greaterThan(today())) {
                return null;
            }

            $order = Order::create([
                'order_number' => Order::generateNumber(),
                'product_id' => $subscription->product_id,
                'subscription_id' => $subscription->id,
                'is_renewal' => true,
                'period_start' => $subscription->next_billing_date,
                'status' => OrderStatus::Pending,
                'product_name' => $subscription->product_name,
                'unit_price' => $subscription->unit_price,
                'quantity' => $subscription->quantity,
                'subtotal' => $subscription->unit_price * $subscription->quantity,
                'discount' => 0,
                'shipping_fee' => $subscription->requires_shipping ? $subscription->shipping_fee : 0,
                'total' => $subscription->renewalTotal(),
                'requires_shipping' => $subscription->requires_shipping,
                'customer_name' => $subscription->customer_name,
                'email' => $subscription->email,
                'phone' => $subscription->phone,
                'address_line1' => $subscription->address_line1,
                'address_line2' => $subscription->address_line2,
                'postcode' => $subscription->postcode,
                'city' => $subscription->city,
                'state' => $subscription->state,
            ]);

            $subscription->forceFill([
                'status' => SubscriptionStatus::PastDue,
                'past_due_since' => now(),
                'last_reminded_at' => now(),
            ])->save();

            return $order;
        });

        if ($order !== null) {
            Mail::to($order->email)->queue(new SubscriptionRenewalDueMail($order));
        }

        return $order;
    }
}
