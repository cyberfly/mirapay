<?php

use App\Livewire\Forms\CouponForm;
use App\Models\Product;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kupon baru')] class extends Component {
    public CouponForm $form;

    public function mount(): void
    {
        $this->authorize('admin');
    }

    #[Computed]
    public function products()
    {
        return Product::orderBy('name')->get();
    }

    public function save(): void
    {
        $this->authorize('admin');

        $this->form->store();

        Flux::toast(variant: 'success', text: 'Kupon dicipta.');

        $this->redirectRoute('admin.coupons.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">Kupon baru</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <x-admin.coupon-fields :form="$form" :products="$this->products" />
        <flux:button type="submit" variant="primary">Simpan</flux:button>
    </form>
</section>
