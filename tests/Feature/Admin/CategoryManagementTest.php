<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

it('adds a category with a slug and rejects duplicates', function () {
    Livewire::test('pages::admin.categories.index')->set('name', 'Produk Digital')->call('add')->assertHasNoErrors();
    expect(Category::first()->slug)->toBe('produk-digital');

    Livewire::test('pages::admin.categories.index')->set('name', 'Produk Digital')->call('add')->assertHasErrors(['name']);
});

it('keeps products when a category is deleted', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    Livewire::test('pages::admin.categories.index')->call('delete', $category->id);

    expect($product->fresh()->category_id)->toBeNull();
});

it('assigns category and featured flag on products', function () {
    $category = Category::factory()->create();

    Livewire::test('pages::admin.products.create')
        ->set('form.name', 'P')->set('form.slug', 'p')->set('form.price', '10')
        ->set('form.category_id', (string) $category->id)->set('form.is_featured', true)
        ->call('save')->assertHasNoErrors();

    expect(Product::first())->category_id->toBe($category->id)->is_featured->toBeTrue();
});
