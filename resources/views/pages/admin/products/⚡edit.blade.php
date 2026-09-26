<?php

use App\Livewire\Forms\ProductForm;
use App\Models\Product;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit produk')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public Product $product;

    public ProductForm $form;

    public function mount(Product $product): void
    {
        $this->authorize('admin');

        $this->product = $product;
        $this->form->setProduct($product);
    }

    public function save(): void
    {
        $this->authorize('admin');

        $this->form->update();
        $this->product->refresh();
        $this->form->image = null;

        Flux::toast(variant: 'success', text: 'Produk dikemas kini.');
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">Edit produk</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <x-admin.product-fields :form="$form" :current-image="$product->imageUrl()" />
        <flux:button type="submit" variant="primary">Simpan</flux:button>
    </form>
</section>
