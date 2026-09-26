<?php

use App\Livewire\Forms\CouponForm;
use App\Models\Coupon;
use App\Models\Product;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit kupon')] class extends Component {
    #[Locked]
    public Coupon $coupon;

    public CouponForm $form;

    public function mount(Coupon $coupon): void
    {
        $this->authorize('admin');

        $this->coupon = $coupon;
        $this->form->setCoupon($coupon);
    }

    #[Computed]
    public function products()
    {
        return Product::orderBy('name')->get();
    }

    public function save(): void
    {
        $this->authorize('admin');

        $this->form->update();

        Flux::toast(variant: 'success', text: 'Kupon dikemas kini.');
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">Edit kupon</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <x-admin.coupon-fields :form="$form" :products="$this->products" />
        <flux:button type="submit" variant="primary">Simpan</flux:button>
    </form>
</section>
