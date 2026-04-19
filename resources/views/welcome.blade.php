<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>MOODI - Delivery con sabor a antojo</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        @livewireStyles

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            [x-cloak] {
                display: none !important;
            }

            .scrollbar-oculta {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }

            .scrollbar-oculta::-webkit-scrollbar {
                display: none;
            }
        </style>
    </head>
    <body x-data="{}" class="bg-gray-50 text-gray-800 antialiased font-sans min-h-screen overflow-x-hidden">
        <header class="hidden md:flex sticky top-0 z-50 bg-white border-b border-orange-100 shadow-sm">
            <div class="mx-auto w-full max-w-7xl px-6 lg:px-8 py-4 flex items-center gap-6">
                <a href="{{ url('/') }}" class="text-3xl font-black tracking-tight text-orange-600 shrink-0">
                    MOODI
                </a>

                <div class="flex-1">
                    <label for="buscador-desktop" class="sr-only">Buscar productos</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 pointer-events-none">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35" />
                                <circle cx="11" cy="11" r="6" />
                            </svg>
                        </span>
                        <input
                            id="buscador-desktop"
                            type="text"
                            placeholder="Buscar 'Doble Cheddar'..."
                            class="w-full h-12 pl-12 pr-4 rounded-full bg-gray-100 border border-transparent text-sm focus:bg-white focus:border-orange-300 focus:ring-4 focus:ring-orange-100 outline-none transition"
                        >
                    </div>
                </div>

                <div class="flex items-center gap-3 lg:gap-4">
                    @auth
                        <span class="text-sm font-semibold text-gray-700">Hola, {{ auth()->user()->name }}</span>
                        <a href="{{ route('dashboard') }}" class="inline-flex h-11 items-center rounded-full bg-orange-500 px-5 text-sm font-bold text-white hover:bg-orange-600 transition">
                            Mi Perfil
                        </a>
                    @else
                        <button
                            type="button"
                            @click="$dispatch('open-auth-modal', { tab: 'login' })"
                            class="text-sm font-semibold text-gray-600 hover:text-orange-600 transition"
                        >
                            Iniciar Sesión
                        </button>

                        <button
                            type="button"
                            @click="$dispatch('open-auth-modal', { tab: 'register' })"
                            class="inline-flex h-11 items-center rounded-full bg-orange-500 px-5 text-sm font-bold text-white hover:bg-orange-600 transition"
                        >
                            Registrarse
                        </button>
                    @endauth

                    <button
                        type="button"
                        x-data="{ cartCount: {{ (int) collect(session('cart', []))->sum('quantity') }} }"
                        @cart-updated.window="cartCount = Number($event.detail.count ?? 0)"
                        aria-label="Carrito"
                        @click="$dispatch('open-cart')"
                        class="relative inline-flex h-11 w-11 items-center justify-center rounded-full border border-orange-100 bg-white text-gray-700 hover:text-orange-600 hover:border-orange-200 transition"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.2 9.2a1 1 0 0 0 .97.8h8.66a1 1 0 0 0 .97-.76L20 7H7" />
                            <circle cx="10" cy="19" r="1.5" />
                            <circle cx="17" cy="19" r="1.5" />
                        </svg>
                        <span class="absolute -top-1 -right-1 h-5 min-w-5 px-1 inline-flex items-center justify-center rounded-full bg-red-500 text-white text-[11px] font-bold" x-text="cartCount">
                            0
                        </span>
                    </button>
                </div>
            </div>
        </header>

        @if (session('success'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-6 lg:px-8">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        <div class="md:hidden bg-white border-b border-orange-100 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto h-16 px-4 relative flex items-center justify-center">
                <a href="{{ url('/') }}" class="text-2xl font-black tracking-tight text-orange-600">MOODI</a>

                <button
                    type="button"
                    x-data="{ cartCount: {{ (int) collect(session('cart', []))->sum('quantity') }} }"
                    @cart-updated.window="cartCount = Number($event.detail.count ?? 0)"
                    aria-label="Carrito"
                    @click="$dispatch('open-cart')"
                    class="absolute right-4 inline-flex h-10 w-10 items-center justify-center rounded-full border border-orange-100 bg-white text-gray-700"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.2 9.2a1 1 0 0 0 .97.8h8.66a1 1 0 0 0 .97-.76L20 7H7" />
                        <circle cx="10" cy="19" r="1.5" />
                        <circle cx="17" cy="19" r="1.5" />
                    </svg>
                    <span class="absolute -top-1 -right-1 h-5 min-w-5 px-1 inline-flex items-center justify-center rounded-full bg-red-500 text-white text-[11px] font-bold" x-text="cartCount">
                        0
                    </span>
                </button>
            </div>
        </div>

        <main class="pb-24 md:pb-0">
            <section class="w-full">
                <div class="w-full bg-gradient-to-r from-orange-600 via-red-500 to-orange-500">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 sm:py-14 md:py-20 lg:py-24 min-h-[260px] sm:min-h-[300px] md:min-h-[380px] flex items-center">
                        <div class="max-w-3xl">
                            <p class="inline-flex items-center px-3 py-1.5 sm:px-4 sm:py-2 rounded-full bg-white/20 text-orange-50 text-[10px] sm:text-xs font-bold uppercase tracking-[0.2em]">
                                Oferta destacada
                            </p>

                            <h1 class="mt-4 sm:mt-5 text-3xl leading-tight sm:text-4xl md:text-5xl lg:text-6xl font-black text-white break-words">
                                ¡Combo del Mes! 20% OFF en la Doble Bacon
                            </h1>

                            <a href="#categorias" class="mt-6 sm:mt-8 inline-flex items-center justify-center rounded-full bg-amber-300 px-6 py-3 sm:px-7 sm:py-3.5 text-sm font-extrabold text-gray-900 hover:bg-amber-200 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-orange-600">
                                Pedir ahora
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section id="categorias" class="sticky top-16 md:top-[81px] z-40 bg-white border-y border-orange-100">
                <div class="mx-auto max-w-7xl px-4 md:px-6 lg:px-8 py-4">
                    <div class="flex gap-3 overflow-x-auto scrollbar-oculta pb-1">
                        @foreach($categories as $category)
                            <a href="#{{ \Illuminate\Support\Str::slug($category->name) }}" class="shrink-0 rounded-full border border-orange-200 bg-gray-50 px-4 py-2 sm:px-5 sm:py-2.5 text-xs sm:text-sm font-bold text-gray-800 hover:bg-orange-100 hover:border-orange-300 hover:text-orange-700 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-1">
                                {{ $category->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 md:px-6 lg:px-8 py-8 md:py-10 space-y-10">
                @foreach($categories as $category)
                    <section id="{{ \Illuminate\Support\Str::slug($category->name) }}" class="scroll-mt-32">
                        <h2 class="text-2xl sm:text-3xl font-black text-gray-900 break-words">{{ $category->name }}</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                            @foreach($category->products as $product)
                                @php
                                    $fallbackImage = 'https://ui-avatars.com/api/?name=' . urlencode($product->name) . '&background=f3f4f6&color=1f2937&size=512';
                                    $productImage = data_get($product, 'image_url')
                                        ?? data_get($product, 'image')
                                        ?? data_get($product, 'photo_url')
                                        ?? data_get($product, 'thumbnail')
                                        ?? data_get($product, 'cover');

                                    $decodedDescription = html_entity_decode((string) ($product->description ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                    $sanitizedDescription = \Illuminate\Support\Str::of(strip_tags($decodedDescription))
                                        ->squish()
                                        ->trim()
                                        ->value();
                                @endphp
                                <article class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden min-w-0">
                                    <img
                                        src="{{ filled($productImage) ? $productImage : $fallbackImage }}"
                                        alt="{{ $product->name }}"
                                        loading="lazy"
                                        decoding="async"
                                        sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
                                        onerror="this.onerror=null;this.src='{{ $fallbackImage }}';"
                                        class="h-44 sm:h-48 w-full object-cover bg-gray-100"
                                    >

                                    <div class="p-4 sm:p-5">
                                        <h3 class="font-bold text-base sm:text-lg text-gray-900 break-words">{{ $product->name }}</h3>
                                        <p class="text-sm text-gray-500 line-clamp-2 mt-1">
                                            {{ $sanitizedDescription !== '' ? $sanitizedDescription : 'Delicioso producto preparado al momento con ingredientes frescos.' }}
                                        </p>

                                        <div class="mt-5 flex items-center justify-between">
                                            <span class="font-black text-base sm:text-lg text-orange-600 whitespace-nowrap">
                                                ${{ number_format($product->price, 0, ',', '.') }}
                                            </span>

                                            <button
                                                type="button"
                                                @click="Livewire.dispatch('cart-add', { productId: {{ $product->id }} }); $dispatch('open-cart')"
                                                class="rounded-xl bg-gray-100 px-3 py-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-800 hover:bg-orange-100 hover:text-orange-700 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
                                            >
                                                + Agregar
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </section>
        </main>

        <nav class="fixed bottom-0 w-full z-50 bg-white border-t border-orange-100 shadow-[0_-8px_24px_rgba(0,0,0,0.08)] flex md:hidden">
            <a href="{{ url('/') }}" class="w-1/4 py-2.5 flex flex-col items-center justify-center text-orange-600">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 9.5V20h14V9.5" />
                </svg>
                <span class="text-[11px] font-semibold mt-1">Inicio</span>
            </a>

            <button type="button" aria-disabled="true" class="w-1/4 py-2.5 flex flex-col items-center justify-center text-gray-400 cursor-not-allowed">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h13" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 18h13" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h.01" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h.01" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 18h.01" />
                </svg>
                <span class="text-[11px] font-semibold mt-1">Pedidos</span>
            </button>

            <button type="button" aria-disabled="true" class="w-1/4 py-2.5 flex flex-col items-center justify-center text-gray-400 cursor-not-allowed">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18" />
                </svg>
                <span class="text-[11px] font-semibold mt-1">Reservar</span>
            </button>

            @auth
                <a href="{{ route('dashboard') }}" class="w-1/4 py-2.5 flex flex-col items-center justify-center text-gray-500 hover:text-orange-600 transition">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 1 0-16 0" />
                        <circle cx="12" cy="8" r="4" />
                    </svg>
                    <span class="text-[11px] font-semibold mt-1">Mi Cuenta</span>
                </a>
            @else
                <button
                    type="button"
                    @click="$dispatch('open-auth-modal', { tab: 'login' })"
                    class="w-1/4 py-2.5 flex flex-col items-center justify-center text-gray-500 hover:text-orange-600 transition"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 1 0-16 0" />
                        <circle cx="12" cy="8" r="4" />
                    </svg>
                    <span class="text-[11px] font-semibold mt-1">Mi Cuenta</span>
                </button>
            @endauth
        </nav>

        @guest
            <livewire:auth-modal />
        @endguest

        <livewire:cart-panel />

        @livewireScripts
    </body>
</html>
