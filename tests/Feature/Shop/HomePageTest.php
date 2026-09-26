<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

it('shows the featured product, promotions and categories', function () {
    $category = Category::factory()->create(['name' => 'Buku']);
    Product::factory()->featured()->create(['name' => 'Utama Hebat']);
    Product::factory()->onSale()->create(['name' => 'Promo Murah', 'category_id' => $category->id]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Produk utama')
        ->assertSee('Utama Hebat')
        ->assertSee('Promosi')
        ->assertSee('Promo Murah')
        ->assertSee('Buku');
});

it('hides inactive products and the promotion section when there are none', function () {
    Product::factory()->inactive()->featured()->create(['name' => 'Tersembunyi']);

    $this->get(route('home'))->assertOk()->assertDontSee('Tersembunyi')->assertDontSee('Promosi');
});

it('shows login links to guests and a dashboard link to users', function () {
    $this->get(route('home'))->assertSee(route('login'), false)->assertDontSee(route('dashboard'), false);

    $this->actingAs(User::factory()->create())
        ->get(route('home'))->assertSee(route('dashboard'), false);
});
