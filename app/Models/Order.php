<?php

namespace App\Models;

use App\Enums\MalaysianState;
use App\Enums\OrderStatus;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $order_number
 * @property int $product_id
 * @property int|null $coupon_id
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
    'order_number', 'product_id', 'coupon_id', 'status', 'product_name', 'unit_price', 'quantity',
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
     * Orders that count as sales: paid or shipped.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeSales(Builder $query): void
    {
        $query->whereIn('status', [OrderStatus::Paid, OrderStatus::Shipped]);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopePaidBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('paid_at', [$from, $to]);
    }

    public function stateLabel(): ?string
    {
        return $this->state ? MalaysianState::tryFrom($this->state)?->label() : null;
    }
}
