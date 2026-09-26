<?php

use App\Models\Category;
use App\Models\Product;
use Livewire\Livewire;

it('lists active products only', function () {
    Product::factory()->create(['name' => 'Kelihatan']);
    Product::factory()->inactive()->create(['name' => 'Tersembunyi']);

    $this->get(route('shop.products.index'))->assertOk()->assertSee('Kelihatan')->assertDontSee('Tersembunyi');
});

it('filters by category and toggles it off', function () {
    $buku = Category::factory()->create(['slug' => 'buku']);
    $lain = Category::factory()->create(['slug' => 'lain']);
    Product::factory()->create(['name' => 'Dalam Buku', 'category_id' => $buku->id]);
    Product::factory()->create(['name' => 'Dalam Lain', 'category_id' => $lain->id]);

    Livewire::test('pages::shop.products')
        ->call('selectCategory', 'buku')
        ->assertSee('Dalam Buku')->assertDontSee('Dalam Lain')
        ->call('selectCategory', 'buku')
        ->assertSee('Dalam Lain');
});

it('searches by name', function () {
    Product::factory()->create(['name' => 'Ebook Raya']);
    Product::factory()->create(['name' => 'Buku Resepi']);

    Livewire::test('pages::shop.products')->set('search', 'Raya')->assertSee('Ebook Raya')->assertDontSee('Buku Resepi');
});

it('paginates', function () {
    Product::factory()->count(13)->create();

    $products = Livewire::test('pages::shop.products')->instance()->products;

    expect($products->count())->toBe(12)->and($products->total())->toBe(13);
});

it('links to category filtered list from the home page', function () {
    $category = Category::factory()->create(['slug' => 'buku']);
    Product::factory()->create(['category_id' => $category->id]);

    $this->get(route('home'))->assertSee(route('shop.products.index', ['kategori' => 'buku']), false);
});
