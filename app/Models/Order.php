<?php

namespace App\Models;

use App\Enums\MalaysianState;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property string $order_number
 * @property int $product_id
 * @property int|null $coupon_id
 * @property int|null $subscription_id
 * @property bool $is_renewal
 * @property Carbon|null $period_start
 * @property OrderStatus $status
 * @property string $product_name
 * @property int $unit_price
 * @property int $quantity
 * @property int $subtotal
 * @property int $discount
 * @property int $shipping_fee
 * @property int $total
 * @property string|null $coupon_code
 * @property bool $requires_shipping
 * @property string $customer_name
 * @property string $email
 * @property string $phone
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $postcode
 * @property string|null $city
 * @property string|null $state
 * @property string $payment_gateway
 * @property string|null $payment_reference
 * @property string|null $stripe_session_id
 * @property Carbon|null $paid_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable([
    'order_number', 'product_id', 'coupon_id', 'subscription_id', 'is_renewal', 'period_start', 'status', 'product_name', 'unit_price', 'quantity',
    'subtotal', 'discount', 'shipping_fee', 'total', 'coupon_code', 'requires_shipping',
    'customer_name', 'email', 'phone', 'address_line1', 'address_line2', 'postcode', 'city', 'state',
    'payment_gateway', 'payment_reference', 'stripe_session_id', 'paid_at', 'shipped_at', 'cancelled_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'requires_shipping' => 'boolean',
            'is_renewal' => 'boolean',
            'period_start' => 'date',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'MP'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }

    public function stateLabel(): ?string
    {
        return $this->state ? MalaysianState::tryFrom($this->state)?->label() : null;
    }
}
