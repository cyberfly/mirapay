<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::factory()->percent(10)->create(['code' => 'RAYA10']);
        Coupon::factory()->fixed(500)->create(['code' => 'JIMAT5']);
    }
}
