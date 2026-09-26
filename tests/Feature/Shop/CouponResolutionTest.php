<?php

use App\Actions\Shop\ResolveCoupon;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

it('resolves a coupon case-insensitively', function () {
    $product = Product::factory()->create();
    $coupon = Coupon::factory()->create(['code' => ' raya10 ']);

    expect((new ResolveCoupon)('Raya10', $product)->is($coupon))->toBeTrue();
});

it('rejects invalid coupons', function (Closure $make, string $message) {
    $product = Product::factory()->create();
    $code = $make($product);

    try {
        (new ResolveCoupon)($code, $product);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors()['couponCode'][0])->toBe($message);
    }
})->with([
    'missing' => [fn () => 'NOPE', 'Kod kupon tidak wujud.'],
    'inactive' => [fn () => Coupon::factory()->inactive()->create()->code, 'Kupon ini tidak aktif.'],
    'not started' => [fn () => Coupon::factory()->notStarted()->create()->code, 'Kupon ini belum bermula.'],
    'expired' => [fn () => Coupon::factory()->expired()->create()->code, 'Kupon ini telah tamat tempoh.'],
    'exhausted' => [fn () => Coupon::factory()->exhausted()->create()->code, 'Kupon ini telah habis digunakan.'],
    'other product' => [fn () => Coupon::factory()->forProduct()->create()->code, 'Kupon ini tidak sah untuk produk ini.'],
]);
