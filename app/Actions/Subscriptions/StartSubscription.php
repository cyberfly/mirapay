<?php

namespace App\Actions\Subscriptions;

use App\Actions\Shop\PlaceOrder;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use DomainException;
use Illuminate\Support\Facades\DB;

class StartSubscription
{
    public function __construct(protected PlaceOrder $placeOrder) {}

    /**
     * Subscribes the customer and returns the first order, which they still have to pay. A coupon
     * only discounts this first order; renewals are billed at the plan's regular price.
     *
     * @param  array{customer_name: string, email: string, phone: string, address_line1?: ?string, address_line2?: ?string, postcode?: ?string, city?: ?string, state?: ?string}  $customer
     */
    public function __invoke(Product $product, int $quantity, ?string $couponCode, array $customer): Order
    {
        if (! $product->isSubscription()) {
            throw new DomainException('Produk ini bukan produk langganan.');
        }

        return DB::transaction(function () use ($product, $quantity, $couponCode, $customer): Order {
            $order = ($this->placeOrder)($product, $quantity, $couponCode, $customer);

            $subscription = Subscription::create([
                'product_id' => $product->id,
                'status' => SubscriptionStatus::Pending,
                'billing_interval' => $product->billing_interval,
                'product_name' => $order->product_name,
                'unit_price' => $order->unit_price,
                'quantity' => $order->quantity,
                'shipping_fee' => $order->shipping_fee,
                'requires_shipping' => $order->requires_shipping,
                'customer_name' => $order->customer_name,
                'email' => $order->email,
                'phone' => $order->phone,
                'address_line1' => $order->address_line1,
                'address_line2' => $order->address_line2,
                'postcode' => $order->postcode,
                'city' => $order->city,
                'state' => $order->state,
            ]);

            $order->forceFill(['subscription_id' => $subscription->id])->save();

            return $order;
        });
    }
}
