<?php

use App\Models\Product;
use App\Models\User;

it('shows an active product', function () {
    $product = Product::factory()->create(['name' => 'Buku Hebat']);

    $this->get(route('shop.products.show', $product))->assertOk()->assertSee('Buku Hebat');
});

it('returns 404 for inactive products', function () {
    $product = Product::factory()->inactive()->create();

    $this->get(route('shop.products.show', $product))->assertNotFound();
});

it('lets admins preview inactive products', function () {
    $product = Product::factory()->inactive()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('shop.products.show', $product))->assertOk();
});

it('shows sold out and hides the form', function () {
    $product = Product::factory()->soldOut()->create();

    $this->get(route('shop.products.show', $product))
        ->assertSee('Habis dijual')
        ->assertDontSee('Beli sekarang');
});

it('sanitizes description html', function () {
    $product = Product::factory()->create([
        'description' => '<script>alert(1)</script><p onclick="x()">Halo <strong>tebal</strong> <a href="javascript:alert(1)">klik</a> <a href="https://a.test">ok</a></p><iframe src="x"></iframe>',
    ]);

    $this->get(route('shop.products.show', $product))
        ->assertDontSee('alert(1)', false)
        ->assertDontSee('onclick', false)
        ->assertDontSee('javascript:', false)
        ->assertDontSee('<iframe', false)
        ->assertSee('<strong>tebal</strong>', false)
        ->assertSee('href="https://a.test"', false);
});
