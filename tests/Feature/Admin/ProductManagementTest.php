<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

it('creates a product converting RM to sen and storing the image', function () {
    Livewire::test('pages::admin.products.create')
        ->set('form.name', 'Buku')
        ->set('form.slug', 'buku')
        ->set('form.price', '19.90')
        ->set('form.compare_at_price', '29.90')
        ->set('form.requires_shipping', true)
        ->set('form.shipping_fee', '8')
        ->set('form.image', UploadedFile::fake()->image('a.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::firstOrFail();
    expect($product->price)->toBe(1990)
        ->and($product->compare_at_price)->toBe(2990)
        ->and($product->shipping_fee)->toBe(800)
        ->and($product->stock)->toBeNull();
    Storage::disk('public')->assertExists($product->image_path);
});

it('deletes the old image when replaced', function () {
    $old = UploadedFile::fake()->image('old.jpg')->store('products', 'public');
    $product = Product::factory()->create(['image_path' => $old]);

    Livewire::test('pages::admin.products.edit', ['product' => $product])
        ->set('form.image', UploadedFile::fake()->image('new.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($product->fresh()->image_path);
});

it('requires a unique slug', function () {
    Product::factory()->create(['slug' => 'taken']);

    Livewire::test('pages::admin.products.create')
        ->set('form.name', 'X')->set('form.slug', 'taken')->set('form.price', '1')
        ->call('save')
        ->assertHasErrors(['form.slug']);
});

it('blocks deleting products that have orders', function () {
    $order = Order::factory()->create();

    Livewire::test('pages::admin.products.index')->call('delete', $order->product_id);

    expect(Product::find($order->product_id))->not->toBeNull();
});

it('deletes products without orders', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::admin.products.index')->call('delete', $product->id);

    expect(Product::find($product->id))->toBeNull();
});

it('sanitizes the description html on save', function () {
    Livewire::test('pages::admin.products.create')
        ->set('form.name', 'X')->set('form.slug', 'x')->set('form.price', '1')
        ->set('form.description', '<p>Halo</p><script>alert(1)</script>')
        ->call('save')->assertHasNoErrors();

    expect(Product::first()->description)->toBe('<p>Halo</p>');
});
