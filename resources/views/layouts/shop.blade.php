<!DOCTYPE html>
<html lang="ms" class="scroll-smooth" style="color-scheme: light">
    <head>
        @include('partials.head')
        {{-- Public site is always light mode; override Flux without touching the stored admin preference. --}}
        <script>
            window.Flux.applyAppearance = () => document.documentElement.classList.remove('dark')
            document.documentElement.classList.remove('dark')
        </script>
        @if ($noindex ?? false)
            <meta name="robots" content="noindex, nofollow" />
        @endif
    </head>
    <body class="flex min-h-screen flex-col bg-white text-slate-800 antialiased">
        <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6">
                <x-app-logo href="{{ route('home') }}" />

                <nav class="hidden items-center gap-1 text-sm font-medium text-slate-600 md:flex">
                    <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 transition hover:bg-slate-100 hover:text-slate-900 {{ request()->routeIs('home') ? 'text-slate-900' : '' }}">Laman utama</a>
                    <a href="{{ route('shop.products.index') }}" class="rounded-lg px-3 py-2 transition hover:bg-slate-100 hover:text-slate-900 {{ request()->routeIs('shop.products.*') ? 'text-slate-900' : '' }}">Produk</a>
                </nav>

                <div class="ms-auto flex items-center gap-2">
                    <a href="{{ route('shop.products.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 md:hidden">Produk</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">Log masuk</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">Daftar</a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6">
            {{ $slot }}
        </main>

        <footer class="border-t border-slate-200 bg-slate-50">
            <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-3 sm:px-6">
                <div class="space-y-3">
                    <x-app-logo href="{{ route('home') }}" />
                    <p class="text-sm leading-relaxed text-slate-500">Beli terus tanpa troli. Pilih produk, isi butiran dan bayar dalam satu langkah.</p>
                </div>
                <div class="space-y-3 text-sm">
                    <p class="font-semibold text-slate-900">Kedai</p>
                    <ul class="space-y-2 text-slate-500">
                        <li><a href="{{ route('home') }}" class="transition hover:text-sky-600">Laman utama</a></li>
                        <li><a href="{{ route('shop.products.index') }}" class="transition hover:text-sky-600">Semua produk</a></li>
                    </ul>
                </div>
                <div class="space-y-3 text-sm">
                    <p class="font-semibold text-slate-900">Akaun</p>
                    <ul class="space-y-2 text-slate-500">
                        @auth
                            <li><a href="{{ route('dashboard') }}" class="transition hover:text-sky-600">Dashboard</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="transition hover:text-sky-600">Log masuk</a></li>
                            @if (Route::has('register'))
                                <li><a href="{{ route('register') }}" class="transition hover:text-sky-600">Daftar</a></li>
                            @endif
                        @endauth
                    </ul>
                </div>
            </div>
            <div class="border-t border-slate-200">
                <p class="mx-auto max-w-6xl px-4 py-5 text-xs text-slate-400 sm:px-6">&copy; {{ now()->year }} {{ config('app.name') }}. Hak cipta terpelihara.</p>
            </div>
        </footer>

        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>

        @fluxScripts
    </body>
</html>
