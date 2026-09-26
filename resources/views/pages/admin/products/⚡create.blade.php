<?php

use App\Livewire\Forms\ProductForm;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Produk baru')] class extends Component {
    use WithFileUploads;

    public ProductForm $form;

    public function mount(): void
    {
        $this->authorize('admin');
    }

    public function save(): void
    {
        $this->authorize('admin');

        $this->form->store();

        Flux::toast(variant: 'success', text: 'Produk dicipta.');

        $this->redirectRoute('admin.products.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">Produk baru</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <x-admin.product-fields :form="$form" />
        <flux:button type="submit" variant="primary">Simpan</flux:button>
    </form>
</section>
