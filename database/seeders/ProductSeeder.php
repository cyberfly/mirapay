<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::factory()->physical(800)->withStock(25)->onSale()->create([
            'name' => 'Buku Panduan Usahawan Online',
            'slug' => 'buku-panduan-usahawan',
            'tagline' => 'Langkah demi langkah memulakan perniagaan online.',
            'description' => '<h2>Apa yang anda dapat</h2><ul><li>200 muka surat</li><li>Templat <strong>kewangan</strong></li><li>Bonus checklist iklan</li></ul>',
            'price' => 5900,
        ]);

        Product::factory()->digital()->create([
            'name' => 'Ebook Resepi Raya',
            'slug' => 'ebook-resepi-raya',
            'tagline' => '50 resepi raya mudah dan sedap.',
            'description' => '<p>Muat turun segera selepas bayaran diterima.</p><ol><li>Rendang</li><li>Lemang</li><li>Kuih tart</li></ol>',
            'price' => 2900,
            'compare_at_price' => 3900,
        ]);

        Product::factory()->physical(600)->soldOut()->create([
            'name' => 'Edisi Terhad: Nota Bergaris',
            'slug' => 'nota-edisi-terhad',
            'tagline' => 'Stok habis dijual.',
            'price' => 3500,
        ]);
    }
}
