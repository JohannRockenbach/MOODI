<x-filament-panels::page>
    {{-- Recetas de Cocina — pantalla a ancho completo con el lenguaje visual Stitch del KDS (ámbar sobre negro). --}}
    <div class="-m-6 -mt-6" style="font-family: 'Inter', 'Figtree', ui-sans-serif, system-ui, sans-serif;">
        <div class="min-h-screen bg-slate-100 text-[17px] text-slate-900 dark:bg-[#10131a] dark:text-[#e0e2ec] transition-colors">

            {{-- ══════════════ HEADER ══════════════ --}}
            <header class="sticky top-0 z-10 border-b border-slate-200 dark:border-[#272a31] bg-white/90 backdrop-blur-md dark:bg-[#1c2026]/90">
                <div class="mx-auto flex max-w-none items-center justify-between gap-4 px-4 py-4 sm:px-8">
                    {{-- Marca + estado --}}
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-xl shadow-md shadow-amber-500/30 dark:bg-[#fbbc48]">
                            <x-heroicon-o-book-open class="h-6 w-6 text-white dark:text-[#422c00]" />
                        </div>
                        <div class="hidden min-w-0 flex-col sm:flex">
                            <span class="text-xl font-bold uppercase tracking-wider text-slate-900 dark:text-[#e0e2ec]">Recetas de Cocina</span>
                            <span class="flex items-center gap-2 text-base font-semibold text-amber-600 dark:text-[#fbbc48]">
                                <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                                Ingredientes y preparación
                            </span>
                        </div>
                    </div>

                    {{-- Reloj + link al KDS --}}
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="hidden items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 dark:bg-[#0b0e15] md:flex">
                            <x-heroicon-o-clock class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" />
                            <div class="text-right leading-tight">
                                <span class="block font-mono text-xl font-black text-slate-900 dark:text-[#e0e2ec]">{{ now()->locale('es')->translatedFormat('H:i') }}</span>
                                <span class="block text-sm font-semibold text-slate-500 dark:text-[#c1c6d5]">{{ now()->locale('es')->translatedFormat('l d/m') }} hs</span>
                            </div>
                        </div>

                        <a href="{{ \App\Filament\Resources\OrderResource::getUrl('kitchen') }}" class="flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-base font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]">
                            <x-heroicon-o-fire class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" />
                            Cocina
                        </a>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-none px-4 py-4 sm:px-8">

                @if (! $this->selectedProduct)
                    {{-- ══════════════ SELECTOR DE PRODUCTOS ══════════════ --}}
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white p-2.5 shadow-sm dark:bg-[#181c22]">
                        <div class="flex items-center gap-2.5">
                            <div class="flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 dark:bg-[#272a31]">
                                <x-heroicon-o-book-open class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" />
                                <span class="text-sm font-bold uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">Recetas</span>
                            </div>
                            <div class="hidden h-6 w-px bg-slate-200 dark:bg-[#414753] sm:block"></div>
                            <span class="text-base text-slate-500 dark:text-[#c1c6d5]">Elegí un producto para ver su receta</span>
                        </div>
                        <span class="flex items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1.5 text-base font-bold text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">
                            <x-heroicon-o-beaker class="h-5 w-5" />
                            {{ $this->availableRecipesProducts === [] ? 'Sin productos' : count($this->availableRecipesProducts).' producto(s) con receta' }}
                        </span>
                    </div>

                    @forelse ($this->availableRecipesProducts as $product)
                        <div wire:key="receta-{{ $product['id'] }}" class="mb-3">
                            <button
                                wire:click="selectRecipe({{ $product['id'] }})"
                                class="flex w-full items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-all hover:border-amber-500 hover:shadow-md active:scale-[0.99] dark:border-[#414753] dark:bg-[#1c2026] dark:hover:border-[#fbbc48]"
                            >
                                <div class="flex min-w-0 items-center gap-4">
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-2xl dark:bg-[#fbbc48]/15">🍔</span>
                                    <div class="min-w-0">
                                        <span class="block truncate text-xl font-black text-slate-900 dark:text-[#e0e2ec]">{{ $product['name'] }}</span>
                                        <span class="mt-0.5 block text-base text-slate-500 dark:text-[#c1c6d5]">
                                            {{ $product['ingredients_count'] }} {{ $product['ingredients_count'] === 1 ? 'ingrediente' : 'ingredientes' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    @if ($product['preparation_time_minutes'])
                                        <span class="flex items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1.5 font-mono text-base font-black text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">
                                            <x-heroicon-o-clock class="h-4 w-4" />
                                            {{ $product['preparation_time_minutes'] }} min
                                        </span>
                                    @endif
                                    <x-heroicon-o-chevron-right class="h-6 w-6 text-slate-400 dark:text-[#c1c6d5]" />
                                </div>
                            </button>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 py-16 text-center dark:border-[#414753]">
                            <span class="text-5xl">📖</span>
                            <p class="mt-3 text-xl font-black text-slate-900 dark:text-[#e0e2ec]">No hay recetas cargadas</p>
                            <p class="mt-1 text-base text-slate-500 dark:text-[#c1c6d5]">Los productos con receta disponible aparecerán acá.</p>
                        </div>
                    @endforelse

                @else
                    {{-- ══════════════ RECETA SELECCIONADA ══════════════ --}}
                    @php $receta = $this->selectedProduct; @endphp

                    <div class="mb-4">
                        <button
                            wire:click="clearRecipe"
                            class="flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-base font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]"
                        >
                            <x-heroicon-o-arrow-left class="h-5 w-5" />
                            Volver a la lista
                        </button>
                    </div>

                    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm dark:bg-[#1c2026]">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 text-base font-bold uppercase tracking-wide text-amber-600 dark:text-[#fbbc48]">
                                    <x-heroicon-o-book-open class="h-5 w-5" />
                                    Receta
                                </div>
                                <h2 class="mt-1 text-3xl font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">{{ $receta['name'] }}</h2>
                                @if ($receta['recipe_name'])
                                    <p class="mt-0.5 text-base font-semibold text-slate-500 dark:text-[#c1c6d5]">Receta: {{ $receta['recipe_name'] }}</p>
                                @endif
                            </div>
                            @if ($receta['preparation_time_minutes'])
                                <span class="flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 font-mono text-lg font-black text-white shadow-md shadow-amber-500/20 dark:bg-[#fbbc48] dark:text-[#422c00]">
                                    <x-heroicon-o-clock class="h-5 w-5" />
                                    {{ $receta['preparation_time_minutes'] }} min
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-3">

                        {{-- ─────────── INGREDIENTES ─────────── --}}
                        <section class="flex min-w-0 flex-col gap-3 xl:col-span-2">
                            <header class="flex items-center justify-between gap-2 rounded-2xl bg-white px-4 py-3 shadow-sm dark:bg-[#272a31]">
                                <h3 class="flex items-center gap-2 text-lg font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">
                                    <x-heroicon-o-beaker class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" />
                                    Ingredientes
                                </h3>
                                <span class="rounded-full bg-amber-500 px-3 py-1 font-mono text-base font-black text-white shadow-sm dark:bg-[#fbbc48] dark:text-[#422c00]">{{ count($receta['ingredients']) }}</span>
                            </header>

                            @if ($receta['has_ingredients'])
                                <div class="flex flex-col gap-2.5">
                                    @foreach ($receta['ingredients'] as $ingredient)
                                        <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-[#414753] dark:bg-[#1c2026]">
                                            <span class="flex min-w-0 items-center gap-3">
                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-xl dark:bg-[#fbbc48]/15">🧂</span>
                                                <span class="truncate text-lg font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $ingredient['name'] }}</span>
                                            </span>
                                            <span class="shrink-0 rounded-lg bg-slate-100 px-3 py-1.5 font-mono text-lg font-black text-amber-700 dark:bg-[#0b0e15] dark:text-[#fbbc48]">
                                                {{ rtrim(rtrim(number_format($ingredient['required_amount'], 3, ',', '.'), '0'), ',') }}
                                                @if ($ingredient['unit'])
                                                    <span class="text-base font-bold text-slate-500 dark:text-[#c1c6d5]">{{ $ingredient['unit'] }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-2xl border border-dashed border-slate-200 py-12 text-center dark:border-[#414753]">
                                    <span class="text-4xl">📭</span>
                                    <p class="mt-2 text-lg font-bold text-slate-900 dark:text-[#e0e2ec]">Esta receta no tiene ingredientes cargados</p>
                                    <p class="mt-1 text-base text-slate-500 dark:text-[#c1c6d5]">Agregá los ingredientes desde la gestión del menú.</p>
                                </div>
                            @endif
                        </section>

                        {{-- ─────────── INSTRUCCIONES ─────────── --}}
                        <section class="flex min-w-0 flex-col gap-3">
                            <header class="flex items-center justify-between gap-2 rounded-2xl bg-white px-4 py-3 shadow-sm dark:bg-[#272a31]">
                                <h3 class="flex items-center gap-2 text-lg font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">
                                    <x-heroicon-o-list-bullet class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" />
                                    Preparación
                                </h3>
                            </header>

                            <div class="rounded-2xl bg-white p-5 shadow-sm dark:bg-[#1c2026]">
                                @if (trim($receta['instructions']) !== '')
                                    <p class="whitespace-pre-line text-base leading-relaxed text-slate-700 dark:text-[#e0e2ec]">{{ $receta['instructions'] }}</p>
                                @else
                                    <p class="text-base italic text-slate-400 dark:text-[#c1c6d5]/70">La receta no tiene instrucciones cargadas.</p>
                                @endif
                            </div>
                        </section>

                    </div>
                @endif
            </main>
        </div>
    </div>
</x-filament-panels::page>