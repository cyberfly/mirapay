<?php

namespace App\Livewire\Forms;

use App\Enums\BillingInterval;
use App\Models\Product;
use App\Support\HtmlSanitizer;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Form;

class ProductForm extends Form
{
    public ?Product $product = null;

    public string $category_id = '';

    public bool $is_featured = false;

    public string $name = '';

    public string $slug = '';

    public string $tagline = '';

    public string $description = '';

    public string $price = '';

    public string $compare_at_price = '';

    public bool $requires_shipping = false;

    public string $shipping_fee = '0.00';

    public string $billing_interval = '';

    public string $stock = '';

    public bool $is_active = true;

    public ?UploadedFile $image = null;

    public function setProduct(Product $product): void
    {
        $this->product = $product;
        $this->category_id = (string) ($product->category_id ?? '');
        $this->is_featured = $product->is_featured;
        $this->name = $product->name;
        $this->slug = $product->slug;
        $this->tagline = $product->tagline ?? '';
        $this->description = $product->description ?? '';
        $this->price = Money::toInput($product->price);
        $this->compare_at_price = $product->compare_at_price ? Money::toInput($product->compare_at_price) : '';
        $this->requires_shipping = $product->requires_shipping;
        $this->shipping_fee = Money::toInput($product->shipping_fee);
        $this->billing_interval = $product->billing_interval->value ?? '';
        $this->stock = $product->stock === null ? '' : (string) $product->stock;
        $this->is_active = $product->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $money = ['regex:/^\d{1,7}(\.\d{1,2})?$/'];

        return [
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'is_featured' => ['boolean'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique('products', 'slug')->ignore($this->product)],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'price' => ['required', ...$money],
            'compare_at_price' => ['nullable', ...$money],
            'requires_shipping' => ['boolean'],
            'shipping_fee' => ['required', ...$money],
            'billing_interval' => ['nullable', Rule::enum(BillingInterval::class)],
            'stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nama',
            'price' => 'harga',
            'compare_at_price' => 'harga asal',
            'shipping_fee' => 'caj pos',
            'billing_interval' => 'kitaran bayaran',
            'stock' => 'stok',
            'image' => 'gambar',
        ];
    }

    public function store(): Product
    {
        $this->validate();

        return Product::create($this->attributes());
    }

    public function update(): void
    {
        $this->validate();

        $attributes = $this->attributes($this->product->image_path);

        if ($this->image !== null) {
            $old = $this->product->image_path;
            $this->product->update($attributes);

            if ($old) {
                Storage::disk('public')->delete($old);
            }
        } else {
            $this->product->update($attributes);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(?string $currentImage = null): array
    {
        try {
            $compareAt = $this->compare_at_price === '' ? null : Money::toSen($this->compare_at_price);
            $price = Money::toSen($this->price);
            $shippingFee = $this->requires_shipping ? Money::toSen($this->shipping_fee) : 0;
        } catch (InvalidArgumentException) {
            abort(422);
        }

        return [
            'category_id' => $this->category_id === '' ? null : (int) $this->category_id,
            'is_featured' => $this->is_featured,
            'name' => $this->name,
            'slug' => $this->slug,
            'tagline' => $this->tagline ?: null,
            'description' => HtmlSanitizer::clean($this->description) ?: null,
            'price' => $price,
            'compare_at_price' => $compareAt,
            'requires_shipping' => $this->requires_shipping,
            'shipping_fee' => $shippingFee,
            'billing_interval' => $this->billing_interval === '' ? null : $this->billing_interval,
            'stock' => $this->stock === '' ? null : (int) $this->stock,
            'is_active' => $this->is_active,
            'image_path' => $this->image?->store('products', 'public') ?? $currentImage,
        ];
    }
}
