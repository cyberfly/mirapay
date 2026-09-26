<?php

use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kategori')] class extends Component {
    public string $name = '';

    public function mount(): void
    {
        $this->authorize('admin');
    }

    #[Computed]
    public function categories()
    {
        return Category::withCount('products')->orderBy('name')->get();
    }

    public function add(): void
    {
        $this->authorize('admin');

        $this->name = trim($this->name);

        $this->validate(['name' => ['required', 'string', 'max:100', 'unique:categories,name']], attributes: ['name' => 'nama']);

        $slug = Str::slug($this->name);

        if ($slug === '' || Category::where('slug', $slug)->exists()) {
            $this->addError('name', 'Nama kategori tidak sesuai atau sudah wujud.');

            return;
        }

        Category::create(['name' => $this->name, 'slug' => $slug]);

        $this->reset('name');
        unset($this->categories);

        Flux::toast(variant: 'success', text: 'Kategori ditambah.');
    }

    public function delete(int $id): void
    {
        $this->authorize('admin');

        Category::findOrFail($id)->delete();
        unset($this->categories);

        Flux::toast(variant: 'success', text: 'Kategori dipadam.');
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">Kategori</flux:heading>

    <form wire:submit="add" class="flex items-start gap-2">
        <div class="flex-1">
            <flux:input wire:model="name" placeholder="Nama kategori baru" />
            <flux:error name="name" />
        </div>
        <flux:button type="submit" variant="primary">Tambah</flux:button>
    </form>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Produk</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->categories as $category)
                <flux:table.row :key="$category->id">
                    <flux:table.cell>{{ $category->name }}</flux:table.cell>
                    <flux:table.cell>{{ $category->products_count }}</flux:table.cell>
                    <flux:table.cell class="text-end">
                        <flux:button size="sm" variant="danger" wire:click="delete({{ $category->id }})" wire:confirm="Padam kategori ini? Produk tidak akan dipadam.">Padam</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
