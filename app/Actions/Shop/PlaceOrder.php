<?php

namespace App\Actions\Shop;

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    public function __construct(
        protected CalculatePrice $calculatePrice,
        protected ResolveCoupon $resolveCoupon,
    ) {}

    /**
     * @param  array{customer_name: string, email: string, phone: string, address_line1?: ?string, address_line2?: ?string, postcode?: ?string, city?: ?string, state?: ?string}  $customer
     */
    public function __invoke(Product $product, int $quantity, ?string $couponCode, array $customer): Order
    {
        return DB::transaction(function () use ($product, $quantity, $couponCode, $customer): Order {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            if (! $product->is_active) {
                throw ValidationException::withMessages(['quantity' => 'Produk ini tidak lagi dijual.']);
            }

            $coupon = filled($couponCode) ? ($this->resolveCoupon)($couponCode, $product) : null;
            $price = ($this->calculatePrice)($product, $quantity, $coupon);

            if ($product->stock !== null) {
                $updated = Product::whereKey($product->id)
                    ->where('stock', '>=', $quantity)
                    ->decrement('stock', $quantity);

                if ($updated === 0) {
                    throw ValidationException::withMessages(['quantity' => 'Stok tidak mencukupi.']);
                }
            }

            if ($coupon !== null) {
                $updated = Coupon::whereKey($coupon->id)
                    ->where(fn ($query) => $query->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'))
                    ->increment('used_count');

                if ($updated === 0) {
                    throw ValidationException::withMessages(['couponCode' => 'Kupon ini telah habis digunakan.']);
                }
            }

            $address = $product->requires_shipping
                ? collect($customer)->only(['address_line1', 'address_line2', 'postcode', 'city', 'state'])->all()
                : [];

            return Order::create([
                'order_number' => Order::generateNumber(),
                'product_id' => $product->id,
                'coupon_id' => $coupon?->id,
                'status' => OrderStatus::Pending,
                'product_name' => $product->name,
                'unit_price' => $price->unitPrice,
                'quantity' => $price->quantity,
                'subtotal' => $price->subtotal,
                'discount' => $price->discount,
                'shipping_fee' => $price->shippingFee,
                'total' => $price->total,
                'coupon_code' => $coupon?->code,
                'requires_shipping' => $product->requires_shipping,
                'customer_name' => $customer['customer_name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'address_line1' => $address['address_line1'] ?? null,
                'address_line2' => $address['address_line2'] ?? null,
                'postcode' => $address['postcode'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
            ]);
        });
    }
}
