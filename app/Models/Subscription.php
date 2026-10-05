<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\MalaysianState;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $product_id
 * @property SubscriptionStatus $status
 * @property BillingInterval $billing_interval
 * @property string $product_name
 * @property int $unit_price
 * @property int $quantity
 * @property int $shipping_fee
 * @property bool $requires_shipping
 * @property string $customer_name
 * @property string $email
 * @property string $phone
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $postcode
 * @property string|null $city
 * @property string|null $state
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $next_billing_date
 * @property Carbon|null $paused_at
 * @property Carbon|null $past_due_since
 * @property Carbon|null $last_reminded_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $ends_at
 */
#[Fillable([
    'product_id', 'status', 'billing_interval', 'product_name', 'unit_price', 'quantity', 'shipping_fee',
    'requires_shipping', 'customer_name', 'email', 'phone', 'address_line1', 'address_line2', 'postcode',
    'city', 'state', 'current_period_start', 'current_period_end', 'next_billing_date', 'paused_at',
    'past_due_since', 'last_reminded_at', 'cancelled_at', 'ends_at',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
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
            'status' => SubscriptionStatus::class,
            'billing_interval' => BillingInterval::class,
            'requires_shipping' => 'boolean',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'next_billing_date' => 'date',
            'paused_at' => 'datetime',
            'past_due_since' => 'datetime',
            'last_reminded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
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
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * What one billing cycle costs the customer.
     */
    public function renewalTotal(): int
    {
        return $this->unit_price * $this->quantity + ($this->requires_shipping ? $this->shipping_fee : 0);
    }

    /**
     * Whether the customer still has what they subscribed to: paid-up, within the grace period,
     * or cancelled/paused but inside a period they already paid for.
     */
    public function hasAccess(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::Active, SubscriptionStatus::PastDue => true,
            SubscriptionStatus::Paused => $this->current_period_end?->isFuture() ?? false,
            SubscriptionStatus::Cancelled => $this->ends_at?->isFuture() ?? false,
            default => false,
        };
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::Pending, SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Paused,
        ], true);
    }

    public function stateLabel(): ?string
    {
        return $this->state ? MalaysianState::tryFrom($this->state)?->label() : null;
    }
}
