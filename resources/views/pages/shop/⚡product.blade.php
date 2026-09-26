<?php

use App\Actions\Shop\CalculatePrice;
use App\Actions\Shop\PlaceOrder;
use App\Actions\Shop\ResolveCoupon;
use App\Enums\MalaysianState;
use App\Models\Product;
use App\Payments\PaymentGateway;
use App\Support\Money;
use App\Support\PriceBreakdown;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::shop')] class extends Component {
    #[Locked]
    public Product $product;

    #[Locked]
    public ?string $appliedCouponCode = null;

    public string|int $quantity = 1;

    public string $couponCode = '';

    public string $customer_name = '';

    public string $email = '';

    public string $phone = '';

    public string $address_line1 = '';

    public string $address_line2 = '';

    public string $postcode = '';

    public string $city = '';

    public string $state = '';

    public function mount(Product $product): void
    {
        abort_unless($product->is_active || auth()->user()?->isAdmin(), 404);

        $this->product = $product;
    }

    public function render(): mixed
    {
        return $this->view()->title($this->product->name);
    }

    #[Computed]
    public function breakdown(): PriceBreakdown
    {
        $coupon = null;

        if ($this->appliedCouponCode !== null) {
            try {
                $coupon = app(ResolveCoupon::class)($this->appliedCouponCode, $this->product);
            } catch (ValidationException) {
                $coupon = null;
            }
        }

        $quantity = max(1, min((int) $this->quantity, $this->product->maxPurchasableQuantity() ?: 1));

        return app(CalculatePrice::class)($this->product, $quantity, $coupon);
    }

    public function applyCoupon(): void
    {
        $key = 'apply-coupon:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['couponCode' => 'Terlalu banyak percubaan. Sila cuba lagi sebentar.']);
        }

        RateLimiter::hit($key, 60);

        $this->validateOnly('couponCode', ['couponCode' => ['required', 'string', 'max:50']], [
            'couponCode.required' => 'Sila masukkan kod kupon.',
        ]);

        $coupon = app(ResolveCoupon::class)($this->couponCode, $this->product);

        $this->appliedCouponCode = $coupon->code;
        $this->couponCode = '';
        unset($this->breakdown);
    }

    public function removeCoupon(): void
    {
        $this->appliedCouponCode = null;
        unset($this->breakdown);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, $this->product->maxPurchasableQuantity())],
            'customer_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'regex:/^(\+?60|0)1\d{8,9}$/'],
        ];

        if ($this->product->requires_shipping) {
            $rules += [
                'address_line1' => ['required', 'string', 'max:255'],
                'address_line2' => ['nullable', 'string', 'max:255'],
                'postcode' => ['required', 'digits:5'],
                'city' => ['required', 'string', 'max:100'],
                'state' => ['required', Rule::enum(MalaysianState::class)],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'email' => 'Format emel tidak sah.',
            'phone.regex' => 'Nombor telefon tidak sah. Contoh: 0123456789.',
            'digits' => ':attribute mestilah :digits digit.',
            'integer' => ':attribute mestilah nombor bulat.',
            'min' => ':attribute minimum :min.',
            'max' => ':attribute maksimum :max.',
            'state.Illuminate\Validation\Rules\Enum' => 'Negeri tidak sah.',
            'enum' => 'Negeri tidak sah.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'quantity' => 'Kuantiti',
            'customer_name' => 'Nama',
            'email' => 'Emel',
            'phone' => 'Nombor telefon',
            'address_line1' => 'Alamat',
            'address_line2' => 'Alamat baris 2',
            'postcode' => 'Poskod',
            'city' => 'Bandar',
            'state' => 'Negeri',
        ];
    }

    public function placeOrder(): void
    {
        $key = 'place-order:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['customer_name' => 'Terlalu banyak percubaan. Sila cuba lagi sebentar.']);
        }

        RateLimiter::hit($key, 60);

        $validated = $this->validate();

        try {
            $order = app(PlaceOrder::class)(
                $this->product,
                (int) $validated['quantity'],
                $this->appliedCouponCode,
                collect($validated)->except('quantity')->all(),
            );
        } catch (ValidationException $e) {
            if ($e->validator->errors()->has('couponCode')) {
                $this->appliedCouponCode = null;
            }

            throw $e;
        }

        $this->redirect(app(PaymentGateway::class)->checkoutUrl($order));
    }
}; ?>

<div class="space-y-8">
    <nav class="flex items-center gap-2 text-sm text-slate-500">
        <a href="{{ route('home') }}" class="transition hover:text-slate-900">Laman utama</a>
        <flux:icon.chevron-right variant="micro" class="text-slate-300" />
        <a href="{{ route('shop.products.index') }}" class="transition hover:text-slate-900">Produk</a>
        @if ($product->category)
            <flux:icon.chevron-right variant="micro" class="text-slate-300" />
            <a href="{{ route('shop.products.index', ['kategori' => $product->category->slug]) }}" class="transition hover:text-slate-900">{{ $product->category->name }}</a>
        @endif
    </nav>

    <div class="grid gap-10 lg:grid-cols-12">
        <div class="space-y-8 lg:col-span-7">
            @if ($product->imageUrl())
                <div class="overflow-hidden rounded-3xl bg-slate-100 ring-1 ring-slate-200/70">
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full object-cover">
                </div>
            @endif

            <div class="space-y-3 lg:hidden">
                <x-shop.product-summary :product="$product" />
            </div>

            @if (filled($product->description))
                <div class="rounded-3xl border border-slate-200 bg-white p-6 md:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">Penerangan produk</h2>
                    <div class="leading-relaxed text-slate-600 [&_a]:text-sky-600 [&_a]:underline [&_h2]:mt-6 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-slate-900 [&_h3]:mt-4 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-slate-900 [&_ol]:my-3 [&_ol]:list-decimal [&_ol]:ps-6 [&_p]:my-3 [&_strong]:text-slate-900 [&_ul]:my-3 [&_ul]:list-disc [&_ul]:ps-6">
                        {!! $product->descriptionHtml() !!}
                    </div>
                </div>
            @endif
        </div>

        <div class="lg:col-span-5">
            <div class="space-y-6">
                <div class="hidden space-y-3 lg:block">
                    <x-shop.product-summary :product="$product" />
                </div>

                @if ($product->isSoldOut())
                    <flux:callout variant="warning" icon="exclamation-triangle" heading="Habis dijual" text="Maaf, stok produk ini telah habis." />
                @else
                    <form wire:submit="placeOrder" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Buat pesanan</h2>
                            <p class="text-sm text-slate-500">Isi butiran anda untuk teruskan ke pembayaran.</p>
                        </div>

                        <flux:input type="number" min="1" max="{{ $product->maxPurchasableQuantity() }}" wire:model.live.debounce.400ms="quantity" label="Kuantiti" />
                        <flux:input wire:model="customer_name" label="Nama" autocomplete="name" />
                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            <flux:input type="email" wire:model="email" label="Emel" autocomplete="email" />
                            <flux:input type="tel" wire:model="phone" label="Nombor telefon" placeholder="0123456789" autocomplete="tel" />
                        </div>

                        @if ($product->requires_shipping)
                            <div class="space-y-5 border-t border-slate-200 pt-5">
                                <p class="text-sm font-semibold text-slate-900">Alamat penghantaran</p>
                                <flux:input wire:model="address_line1" label="Alamat" autocomplete="address-line1" />
                                <flux:input wire:model="address_line2" label="Alamat baris 2 (pilihan)" autocomplete="address-line2" />
                                <div class="grid grid-cols-2 gap-4">
                                    <flux:input wire:model="postcode" label="Poskod" inputmode="numeric" autocomplete="postal-code" />
                                    <flux:input wire:model="city" label="Bandar" autocomplete="address-level2" />
                                </div>
                                <flux:select wire:model="state" label="Negeri" placeholder="Pilih negeri">
                                    @foreach (MalaysianState::cases() as $option)
                                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif

                        <div class="border-t border-slate-200 pt-5">
                            @if ($appliedCouponCode)
                                <div class="flex items-center justify-between rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-emerald-100">
                                    <span class="flex items-center gap-2"><flux:icon.ticket variant="mini" /> Kupon <strong>{{ $appliedCouponCode }}</strong> digunakan</span>
                                    <flux:button type="button" size="xs" variant="ghost" wire:click="removeCoupon">Buang</flux:button>
                                </div>
                            @else
                                <div class="flex items-start gap-2">
                                    <div class="flex-1">
                                        <flux:input wire:model="couponCode" wire:keydown.enter.prevent="applyCoupon" placeholder="Kod kupon" icon="ticket" />
                                        <flux:error name="couponCode" />
                                    </div>
                                    <flux:button type="button" wire:click="applyCoupon">Guna</flux:button>
                                </div>
                            @endif
                            @if ($appliedCouponCode)
                                <flux:error name="couponCode" />
                            @endif
                        </div>

                        <dl class="space-y-2 rounded-2xl bg-slate-50 p-4 text-sm text-slate-600">
                            <div class="flex justify-between"><dt>Subjumlah</dt><dd class="text-slate-900">{{ Money::format($this->breakdown->subtotal) }}</dd></div>
                            @if ($this->breakdown->discount > 0)
                                <div class="flex justify-between text-emerald-600"><dt>Diskaun</dt><dd>-{{ Money::format($this->breakdown->discount) }}</dd></div>
                            @endif
                            @if ($product->requires_shipping)
                                <div class="flex justify-between"><dt>Pos</dt><dd class="text-slate-900">{{ Money::format($this->breakdown->shippingFee) }}</dd></div>
                            @endif
                            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold text-slate-900"><dt>Jumlah</dt><dd>{{ Money::format($this->breakdown->total) }}</dd></div>
                        </dl>

                        <button type="submit" wire:loading.attr="disabled" wire:target="placeOrder" class="flex w-full items-center justify-center gap-2 rounded-xl bg-sky-600 px-6 py-3.5 font-semibold text-white shadow-sm shadow-sky-600/20 transition hover:bg-sky-700 disabled:opacity-60">
                            <flux:icon.loading variant="micro" wire:loading wire:target="placeOrder" />
                            Beli sekarang
                        </button>
                        <p class="flex items-center justify-center gap-1.5 text-xs text-slate-400">
                            <flux:icon.lock-closed variant="micro" />
                            Pembayaran selamat
                        </p>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
