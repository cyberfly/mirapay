<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $book = Product::where('slug', 'buku-panduan-usahawan')->firstOrFail();
        $ebook = Product::where('slug', 'ebook-resepi-raya')->firstOrFail();

        Order::factory()->forProduct($book)->withAddress()->create();
        Order::factory()->forProduct($book)->withAddress()->paid()->create();
        Order::factory()->forProduct($ebook)->paid()->create();
        Order::factory()->forProduct($ebook)->cancelled()->create();
    }
}
