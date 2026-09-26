<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $buku = Category::create(['name' => 'Buku', 'slug' => 'buku']);
        $digital = Category::create(['name' => 'Produk Digital', 'slug' => 'produk-digital']);

        Product::whereIn('slug', ['buku-panduan-usahawan', 'nota-edisi-terhad'])->update(['category_id' => $buku->id]);
        Product::where('slug', 'ebook-resepi-raya')->update(['category_id' => $digital->id]);
        Product::where('slug', 'buku-panduan-usahawan')->update(['is_featured' => true]);
    }
}
