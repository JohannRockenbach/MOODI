<x-filament-panels::page>
    {{-- KDS Cocina — pantalla operativa a ancho completo con el lenguaje visual Stitch del TPV (ámbar sobre negro). --}}
    <div wire:poll.5s="loadOrders" class="-m-6 -mt-6" style="font-family: 'Inter', 'Figtree', ui-sans-serif, system-ui, sans-serif;">
        <div class="min-h-screen bg-slate-100 text-[17px] text-slate-900 dark:bg-[#10131a] dark:text-[#e0e2ec] transition-colors">

            {{-- ══════════════ HEADER KDS ══════════════ --}}
            <header class="sticky top-0 z-10 border-b border-slate-200 dark:border-[#272a31] bg-white/90 backdrop-blur-md dark:bg-[#1c2026]/90">
                <div class="mx-auto flex max-w-none items-center justify-between gap-4 px-4 py-4 sm:px-8">
                    {{-- Marca + estado --}}
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-xl shadow-md shadow-amber-500/30 dark:bg-[#fbbc48]">
                            <span class="leading-none">🔥</span>
                        </div>
                        <div class="hidden min-w-0 flex-col sm:flex">
                            <span class="text-xl font-bold uppercase tracking-wider text-slate-900 dark:text-[#e0e2ec]">KDS Cocina</span>
                            <span class="flex items-center gap-2 text-base font-semibold text-amber-600 dark:text-[#fbbc48]">
                                <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                                En Servicio
                            </span>
                        </div>
                    </div>

                    {{-- Reloj + terminal links --}}
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="hidden items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 dark:bg-[#0b0e15] md:flex">
                            <x-heroicon-o-clock class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" />
                            <div class="text-right leading-tight">
                                <span class="block font-mono text-xl font-black text-slate-900 dark:text-[#e0e2ec]">{{ now()->format('H:i') }}</span>
                                <span class="block text-sm font-semibold text-slate-500 dark:text-[#c1c6d5]">{{ now()->format('l d/m') }} hs</span>
                            </div>
                        </div>

                        <div class="hidden items-center gap-1 xl:flex">
                            <a href="{{ \App\Filament\Pages\TableMap::getUrl() }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]">Mapa de Mesas</a>
                            <a href="{{ \App\Filament\Pages\CrearPedido::getUrl() }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]">Crear Pedido</a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-none px-4 py-4 sm:px-8">
                {{-- ══════════════ BARRA DE RESUMEN ══════════════ --}}
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white p-2.5 shadow-sm dark:bg-[#181c22]">
                    <div class="flex items-center gap-2.5">
                        <div class="flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 dark:bg-[#272a31]">
                            <x-heroicon-o-fire class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" />
                            <span class="text-sm font-bold uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">Cocina</span>
                        </div>
                        <div class="hidden h-6 w-px bg-slate-200 dark:bg-[#414753] sm:block"></div>
                        <span class="text-base text-slate-500 dark:text-[#c1c6d5]">Estado de comandas en tiempo real</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="flex items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1.5 text-base font-bold text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">
                            <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                            Pendientes
                            <span class="rounded-full bg-amber-500 px-2 py-0.5 font-mono font-black text-white dark:bg-[#fbbc48] dark:text-[#422c00]">{{ $pendingOrders->count() }}</span>
                        </span>
                        <span class="flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-base font-bold text-slate-600 dark:bg-[#272a31] dark:text-[#c1c6d5]">
                            <span class="inline-block h-2 w-2 rounded-full bg-slate-400"></span>
                            En Preparación
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 font-mono font-black text-slate-700 dark:bg-[#32353c] dark:text-[#e0e2ec]">{{ $processingOrders->count() }}</span>
                        </span>
                        <span class="flex items-center gap-1.5 rounded-full bg-green-500/10 px-3 py-1.5 text-base font-bold text-green-700 dark:bg-green-500/15 dark:text-green-400">
                            <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                            Listos
                            <span class="rounded-full bg-green-500 px-2 py-0.5 font-mono font-black text-white dark:text-[#052f1a]">{{ $readyOrders->count() }}</span>
                        </span>
                    </div>
                </div>

                {{-- ══════════════ GRID 3 COLUMNAS ══════════════ --}}
                <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-3">

                    {{-- ─────────── COLUMNA: PENDIENTES ⏳ ─────────── --}}
                    <section class="flex min-w-0 flex-col gap-3">
                        <header class="flex items-center justify-between gap-2 rounded-2xl bg-white px-4 py-3 shadow-sm dark:bg-[#272a31]">
                            <h2 class="flex items-center gap-2 text-lg font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">
                                <span class="text-2xl leading-none">⏳</span> Pendientes
                            </h2>
                            <span class="rounded-full bg-amber-500 px-3 py-1 font-mono text-base font-black text-white shadow-sm dark:bg-[#fbbc48] dark:text-[#422c00]">{{ $pendingOrders->count() }}</span>
                        </header>

                        <div class="flex max-h-[calc(100vh-290px)] flex-col gap-3 overflow-y-auto pr-1 [scrollbar-width:thin]">
                            @forelse ($pendingOrders as $order)
                                @php
                                    $badge = match($order->type) {
                                        'salon' => ['class' => 'bg-amber-500 text-white dark:bg-[#fbbc48] dark:text-[#422c00]', 'label' => 'MESA #' . ($order->table->number ?? 'N/A')],
                                        'para_llevar' => ['class' => 'bg-amber-100 text-amber-700 dark:bg-[#fbbc48]/15 dark:text-[#fbbc48]', 'label' => '🥡 PARA LLEVAR'],
                                        'delivery' => ['class' => 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400', 'label' => '🛵 DELIVERY'],
                                        default => ['class' => 'bg-slate-100 text-slate-600 dark:bg-[#272a31] dark:text-[#c1c6d5]', 'label' => '📄 PEDIDO'],
                                    };
                                @endphp

                                <div wire:key="pend-{{ $order->id }}" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-[#414753] dark:bg-[#1c2026]">
                                    {{-- Header de card --}}
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                                            <span class="rounded-lg px-2.5 py-1 text-sm font-black tracking-wide {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                            <span class="font-mono text-lg font-black text-slate-900 dark:text-[#e0e2ec]">#{{ $order->id }}</span>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <span class="block font-mono text-base font-bold text-slate-500 dark:text-[#c1c6d5]">{{ $order->created_at->format('H:i') }}</span>
                                            <span class="block text-sm font-semibold text-amber-600 dark:text-[#fbbc48]">⏱ {{ $order->created_at->diffForHumans(null, true) }}</span>
                                        </div>
                                    </div>

                                    {{-- Ítems (sin Bebidas) --}}
                                    <div class="flex flex-col gap-1.5 border-t border-slate-100 pt-2.5 dark:border-[#272a31]">
                                        @foreach ($order->orderProducts->where('product.category.name', '!=', 'Bebidas') as $item)
                                            <div class="flex items-start gap-2">
                                                <span class="mt-0.5 shrink-0 rounded bg-slate-100 px-2 py-0.5 font-mono text-base font-black text-slate-700 dark:bg-[#0b0e15] dark:text-[#e0e2ec]">{{ $item->quantity }}x</span>
                                                <span class="text-base font-semibold text-slate-900 dark:text-[#e0e2ec]">{{ $item->product->name ?? 'Producto' }}</span>
                                            </div>
                                            @if (trim((string) $item->notes) !== '')
                                                <p class="-mt-1 flex items-center gap-1 pl-9 text-sm text-amber-600 dark:text-[#fbbc48]">
                                                    <x-heroicon-o-pencil-square class="h-4 w-4 shrink-0" /> {{ $item->notes }}
                                                </p>
                                            @endif
                                        @endforeach
                                    </div>

                                    {{-- Acciones --}}
                                    <div class="flex flex-col gap-2 border-t border-slate-100 pt-2.5 dark:border-[#272a31]">
                                        <button
                                            wire:click="startProcessing({{ $order->id }})"
                                            wire:loading.attr="disabled"
                                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 py-3 text-base font-bold text-white shadow-sm transition-all hover:bg-amber-400 active:scale-[0.98] dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]"
                                        >
                                            ▶️ EMPEZAR
                                        </button>
                                        <button
                                            wire:click="cancelOrder({{ $order->id }})"
                                            wire:loading.attr="disabled"
                                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-red-50 py-3 text-base font-bold text-red-700 transition-all hover:bg-red-100 active:scale-[0.98] dark:bg-[#93000a]/20 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/30"
                                        >
                                            ❌ CANCELAR
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-200 py-12 text-center dark:border-[#414753]">
                                    <span class="text-4xl">✅</span>
                                    <p class="mt-2 text-base font-bold text-slate-600 dark:text-[#e0e2ec]">No hay pedidos pendientes</p>
                                    <p class="mt-1 text-sm text-slate-400 dark:text-[#c1c6d5]/70">Las nuevas comandas aparecen acá.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    {{-- ─────────── COLUMNA: EN PREPARACIÓN 🔥 ─────────── --}}
                    <section class="flex min-w-0 flex-col gap-3">
                        <header class="flex items-center justify-between gap-2 rounded-2xl bg-white px-4 py-3 shadow-sm dark:bg-[#272a31]">
                            <h2 class="flex items-center gap-2 text-lg font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">
                                <span class="text-2xl leading-none">🔥</span> En Preparación
                            </h2>
                            <span class="rounded-full bg-slate-100 px-3 py-1 font-mono text-base font-black text-slate-700 dark:bg-[#32353c] dark:text-[#e0e2ec]">{{ $processingOrders->count() }}</span>
                        </header>

                        <div class="flex max-h-[calc(100vh-290px)] flex-col gap-3 overflow-y-auto pr-1 [scrollbar-width:thin]">
                            @forelse ($processingOrders as $order)
                                @php
                                    $badge = match($order->type) {
                                        'salon' => ['class' => 'bg-amber-500 text-white dark:bg-[#fbbc48] dark:text-[#422c00]', 'label' => 'MESA #' . ($order->table->number ?? 'N/A')],
                                        'para_llevar' => ['class' => 'bg-amber-100 text-amber-700 dark:bg-[#fbbc48]/15 dark:text-[#fbbc48]', 'label' => '🥡 PARA LLEVAR'],
                                        'delivery' => ['class' => 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400', 'label' => '🛵 DELIVERY'],
                                        default => ['class' => 'bg-slate-100 text-slate-600 dark:bg-[#272a31] dark:text-[#c1c6d5]', 'label' => '📄 PEDIDO'],
                                    };
                                @endphp

                                <div wire:key="proc-{{ $order->id }}" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-[#414753] dark:bg-[#1c2026]">
                                    {{-- Header de card --}}
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                                            <span class="rounded-lg px-2.5 py-1 text-sm font-black tracking-wide {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                            <span class="font-mono text-lg font-black text-slate-900 dark:text-[#e0e2ec]">#{{ $order->id }}</span>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <span class="block font-mono text-base font-bold text-slate-500 dark:text-[#c1c6d5]">{{ $order->created_at->format('H:i') }}</span>
                                            <span class="block text-sm font-semibold text-amber-600 dark:text-[#fbbc48]">⏱ {{ $order->created_at->diffForHumans(null, true) }}</span>
                                        </div>
                                    </div>

                                    {{-- Ítems (sin Bebidas) --}}
                                    <div class="flex flex-col gap-1.5 border-t border-slate-100 pt-2.5 dark:border-[#272a31]">
                                        @foreach ($order->orderProducts->where('product.category.name', '!=', 'Bebidas') as $item)
                                            <div class="flex items-start gap-2">
                                                <span class="mt-0.5 shrink-0 rounded bg-slate-100 px-2 py-0.5 font-mono text-base font-black text-slate-700 dark:bg-[#0b0e15] dark:text-[#e0e2ec]">{{ $item->quantity }}x</span>
                                                <span class="text-base font-semibold text-slate-900 dark:text-[#e0e2ec]">{{ $item->product->name ?? 'Producto' }}</span>
                                            </div>
                                            @if (trim((string) $item->notes) !== '')
                                                <p class="-mt-1 flex items-center gap-1 pl-9 text-sm text-amber-600 dark:text-[#fbbc48]">
                                                    <x-heroicon-o-pencil-square class="h-4 w-4 shrink-0" /> {{ $item->notes }}
                                                </p>
                                            @endif
                                        @endforeach
                                    </div>

                                    {{-- Acciones --}}
                                    <div class="flex flex-col gap-2 border-t border-slate-100 pt-2.5 dark:border-[#272a31]">
                                        <button
                                            wire:click="markAsReady({{ $order->id }})"
                                            wire:loading.attr="disabled"
                                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 py-3 text-base font-bold text-white shadow-sm transition-all hover:bg-amber-400 active:scale-[0.98] dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]"
                                        >
                                            ✅ LISTO
                                        </button>
                                        <button
                                            wire:click="cancelOrder({{ $order->id }})"
                                            wire:loading.attr="disabled"
                                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-red-50 py-3 text-base font-bold text-red-700 transition-all hover:bg-red-100 active:scale-[0.98] dark:bg-[#93000a]/20 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/30"
                                        >
                                            ❌ CANCELAR
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-200 py-12 text-center dark:border-[#414753]">
                                    <span class="text-4xl">🍳</span>
                                    <p class="mt-2 text-base font-bold text-slate-600 dark:text-[#e0e2ec]">No hay pedidos en preparación</p>
                                    <p class="mt-1 text-sm text-slate-400 dark:text-[#c1c6d5]/70">Empezá una comanda desde Pendientes.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    {{-- ─────────── COLUMNA: LISTO PARA RETIRAR ✅ ─────────── --}}
                    <section class="flex min-w-0 flex-col gap-3">
                        <header class="flex items-center justify-between gap-2 rounded-2xl bg-white px-4 py-3 shadow-sm dark:bg-[#272a31]">
                            <h2 class="flex items-center gap-2 text-lg font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">
                                <span class="text-2xl leading-none">✅</span> Listo para retirar
                            </h2>
                            <span class="rounded-full bg-green-100 px-3 py-1 font-mono text-base font-black text-green-700 dark:bg-green-500/15 dark:text-green-400">{{ $readyOrders->count() }}</span>
                        </header>

                        <div class="flex max-h-[calc(100vh-290px)] flex-col gap-3 overflow-y-auto pr-1 [scrollbar-width:thin]">
                            @forelse ($readyOrders as $order)
                                @php
                                    $badge = match($order->type) {
                                        'salon' => ['class' => 'bg-amber-500 text-white dark:bg-[#fbbc48] dark:text-[#422c00]', 'label' => 'MESA #' . ($order->table->number ?? 'N/A')],
                                        'para_llevar' => ['class' => 'bg-amber-100 text-amber-700 dark:bg-[#fbbc48]/15 dark:text-[#fbbc48]', 'label' => '🥡 PARA LLEVAR'],
                                        'delivery' => ['class' => 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400', 'label' => '🛵 DELIVERY'],
                                        default => ['class' => 'bg-slate-100 text-slate-600 dark:bg-[#272a31] dark:text-[#c1c6d5]', 'label' => '📄 PEDIDO'],
                                    };
                                @endphp

                                <div wire:key="ready-{{ $order->id }}" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-[#414753] dark:bg-[#1c2026]">
                                    {{-- Header de card --}}
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                                            <span class="rounded-lg px-2.5 py-1 text-sm font-black tracking-wide {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                            <span class="font-mono text-lg font-black text-slate-900 dark:text-[#e0e2ec]">#{{ $order->id }}</span>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <span class="block font-mono text-base font-bold text-slate-500 dark:text-[#c1c6d5]">{{ $order->created_at->format('H:i') }}</span>
                                            <span class="block text-sm font-semibold text-green-700 dark:text-green-400">⏱ {{ $order->created_at->diffForHumans(null, true) }}</span>
                                        </div>
                                    </div>

                                    {{-- Ítems (sin Bebidas) --}}
                                    <div class="flex flex-col gap-1.5 border-t border-slate-100 pt-2.5 dark:border-[#272a31]">
                                        @foreach ($order->orderProducts->where('product.category.name', '!=', 'Bebidas') as $item)
                                            <div class="flex items-start gap-2">
                                                <span class="mt-0.5 shrink-0 rounded bg-slate-100 px-2 py-0.5 font-mono text-base font-black text-slate-700 dark:bg-[#0b0e15] dark:text-[#e0e2ec]">{{ $item->quantity }}x</span>
                                                <span class="text-base font-semibold text-slate-900 dark:text-[#e0e2ec]">{{ $item->product->name ?? 'Producto' }}</span>
                                            </div>
                                            @if (trim((string) $item->notes) !== '')
                                                <p class="-mt-1 flex items-center gap-1 pl-9 text-sm text-amber-600 dark:text-[#fbbc48]">
                                                    <x-heroicon-o-pencil-square class="h-4 w-4 shrink-0" /> {{ $item->notes }}
                                                </p>
                                            @endif
                                        @endforeach
                                    </div>

                                    {{-- Acciones --}}
                                    <div class="flex flex-col gap-2 border-t border-slate-100 pt-2.5 dark:border-[#272a31]">
                                        <button
                                            wire:click="markAsCompleted({{ $order->id }})"
                                            wire:loading.attr="disabled"
                                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-green-500 py-3 text-base font-bold text-white shadow-sm transition-all hover:bg-green-600 active:scale-[0.98] dark:bg-green-500 dark:text-[#052f1a] dark:hover:bg-green-400"
                                        >
                                            ✅ ENTREGADO
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-200 py-12 text-center dark:border-[#414753]">
                                    <span class="text-4xl">📭</span>
                                    <p class="mt-2 text-base font-bold text-slate-600 dark:text-[#e0e2ec]">No hay pedidos listos</p>
                                    <p class="mt-1 text-sm text-slate-400 dark:text-[#c1c6d5]/70">Las comandas listas aparecen acá.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>

                </div>
            </main>
        </div>
    </div>
</x-filament-panels::page>