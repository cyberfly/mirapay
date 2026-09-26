<!DOCTYPE html>
<html lang="ms">
    <head>
        @include('partials.head')
        @if ($noindex ?? false)
            <meta name="robots" content="noindex, nofollow" />
        @endif
    </head>
    <body class="min-h-screen bg-sky-50 text-slate-900 antialiased dark:bg-slate-900 dark:text-slate-100">
        <header class="border-b border-sky-100 bg-white dark:border-slate-700 dark:bg-slate-800">
            <div class="mx-auto flex max-w-5xl items-center px-4 py-3">
                <x-app-logo href="{{ route('home') }}" />

                <nav class="ms-auto flex items-center gap-2">
                    <flux:button size="sm" variant="ghost" :href="route('shop.products.index')">Produk</flux:button>
                    @auth
                        <flux:button size="sm" variant="primary" :href="route('dashboard')">Dashboard</flux:button>
                    @else
                        <flux:button size="sm" variant="ghost" :href="route('login')">Log masuk</flux:button>
                        @if (Route::has('register'))
                            <flux:button size="sm" variant="primary" :href="route('register')">Daftar</flux:button>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-5xl px-4 py-8 text-center text-sm text-zinc-500">
            &copy; {{ now()->year }} {{ config('app.name') }}
        </footer>

        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>

        @fluxScripts
    </body>
</html>
