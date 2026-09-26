<?php

namespace App\Models;

use App\Enums\CouponType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property int $value
 * @property int|null $product_id
 * @property int|null $max_uses
 * @property int $used_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
#[Fillable(['code', 'type', 'value', 'product_id', 'max_uses', 'starts_at', 'expires_at', 'is_active'])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value): string => strtoupper(trim($value)));
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
