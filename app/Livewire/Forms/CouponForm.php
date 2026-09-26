<?php

namespace App\Livewire\Forms;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CouponForm extends Form
{
    public ?Coupon $coupon = null;

    public string $code = '';

    public string $type = 'percent';

    public string $value = '';

    public string $product_id = '';

    public string $max_uses = '';

    public string $starts_at = '';

    public string $expires_at = '';

    public bool $is_active = true;

    public function setCoupon(Coupon $coupon): void
    {
        $this->coupon = $coupon;
        $this->code = $coupon->code;
        $this->type = $coupon->type->value;
        $this->value = $coupon->type === CouponType::Fixed ? Money::toInput($coupon->value) : (string) $coupon->value;
        $this->product_id = (string) ($coupon->product_id ?? '');
        $this->max_uses = (string) ($coupon->max_uses ?? '');
        $this->starts_at = $coupon->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->expires_at = $coupon->expires_at?->format('Y-m-d\TH:i') ?? '';
        $this->is_active = $coupon->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $value = $this->type === CouponType::Percent->value
            ? ['required', 'integer', 'between:1,100']
            : ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'];

        return [
            'code' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => $value,
            'product_id' => ['nullable', Rule::exists('products', 'id')],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'code' => 'kod',
            'type' => 'jenis',
            'value' => 'nilai',
            'product_id' => 'produk',
            'max_uses' => 'had penggunaan',
            'starts_at' => 'tarikh mula',
            'expires_at' => 'tarikh tamat',
        ];
    }

    public function store(): Coupon
    {
        $this->validateWithUniqueCode();

        return Coupon::create($this->attributes());
    }

    public function update(): void
    {
        $this->validateWithUniqueCode();

        $this->coupon->update($this->attributes());
    }

    protected function validateWithUniqueCode(): void
    {
        $this->code = strtoupper(trim($this->code));

        $this->validate();

        $this->validate([
            'code' => [Rule::unique('coupons', 'code')->ignore($this->coupon)],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->type === CouponType::Fixed->value ? Money::toSen($this->value) : (int) $this->value,
            'product_id' => $this->product_id === '' ? null : (int) $this->product_id,
            'max_uses' => $this->max_uses === '' ? null : (int) $this->max_uses,
            'starts_at' => $this->starts_at ?: null,
            'expires_at' => $this->expires_at ?: null,
            'is_active' => $this->is_active,
        ];
    }
}
