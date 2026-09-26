<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $category_id
 * @property bool $is_featured
 * @property string $name
 * @property string $slug
 * @property string|null $tagline
 * @property string|null $description
 * @property string|null $image_path
 * @property int $price
 * @property int|null $compare_at_price
 * @property bool $requires_shipping
 * @property int $shipping_fee
 * @property int|null $stock
 * @property bool $is_active
 */
#[Fillable(['category_id', 'is_featured', 'name', 'slug', 'tagline', 'description', 'image_path', 'price', 'compare_at_price', 'requires_shipping', 'shipping_fee', 'stock', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_shipping' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeOnPromotion(Builder $query): void
    {
        $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price');
    }

    public function discountPercent(): ?int
    {
        if (! $this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }

        return (int) round(($this->compare_at_price - $this->price) / $this->compare_at_price * 100);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isSoldOut(): bool
    {
        return $this->stock !== null && $this->stock <= 0;
    }

    public function maxPurchasableQuantity(): int
    {
        return $this->stock === null ? 99 : max(0, min($this->stock, 99));
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function descriptionHtml(): string
    {
        return HtmlSanitizer::clean($this->description);
    }
}
