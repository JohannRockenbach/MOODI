<x-filament-panels::page>
    {{-- Punto de Venta Mozo — Crear Pedido Rápido (mockup Stitch "crear_pedido") --}}
    <div class="-m-6 -mt-6" style="font-family: 'Inter', 'Figtree', ui-sans-serif, system-ui, sans-serif;">
        <div class="min-h-screen bg-slate-100 text-slate-900 dark:bg-[#10131a] dark:text-[#e0e2ec] transition-colors">

            {{-- ══════════════ HEADER POS ══════════════ --}}
            <header class="sticky top-0 z-30 border-b border-slate-200 dark:border-[#272a31] bg-white/90 backdrop-blur-md dark:bg-[#1c2026]/90">
                <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    {{-- Marca + estado --}}
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-md shadow-amber-500/30">
                            <x-heroicon-o-map class="h-5 w-5" />
                        </div>
                        <div class="hidden min-w-0 flex-col sm:flex">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-[#e0e2ec]">MesaMap POS</span>
                            <span class="flex items-center gap-1.5 text-xs font-semibold text-amber-600 dark:text-[#fbbc48]">
                                <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                                En Servicio
                            </span>
                        </div>
                    </div>

                    {{-- Mesa seleccionada (o selector si no viene del mapa) --}}
                    <div class="hidden min-w-0 items-center gap-3 rounded-xl bg-slate-100 px-4 py-2 dark:bg-[#272a31] md:flex">
                        <x-heroicon-o-table-cells class="h-4 w-4 shrink-0 text-amber-600 dark:text-[#fbbc48]" />
                        @if ($this->selectedTableId && $this->selectedTable)
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-slate-900 dark:text-[#e0e2ec]">
                                    <span>Mesa #{{ $this->selectedTable->number }}</span>
                                    @if ($tableLockedFromMap)
                                        <x-heroicon-o-lock-closed class="h-3 w-3 text-amber-600 dark:text-[#fbbc48]" />
                                    @endif
                                </div>
                                <div class="truncate text-[11px] text-slate-500 dark:text-[#c1c6d5]">
                                    {{ $this->selectedTable->location }} · Capacidad: {{ $this->selectedTable->capacity }} comensales
                                </div>
                            </div>
                        @elseif ($this->orderType === \App\Filament\Pages\CrearPedido::TYPE_SALON)
                            <div class="flex items-center gap-1.5">
                                <select
                                    wire:model="selectedTableId"
                                    class="rounded-lg border-0 bg-white px-2 py-1.5 text-xs font-semibold text-slate-900 shadow-sm outline-none ring-1 ring-slate-200 focus:ring-amber-500 dark:bg-[#0b0e15] dark:text-[#e0e2ec] dark:ring-[#414753]"
                                >
                                    <option value="">Elegí una mesa…</option>
                                    @foreach ($this->tables as $table)
                                        <option value="{{ $table['id'] }}">Mesa #{{ $table['number'] }} — {{ $table['location'] }} ({{ $table['status_label'] }})</option>
                                    @endforeach
                                </select>
                                <span class="hidden text-[11px] text-slate-500 dark:text-[#c1c6d5] lg:inline">Elegí la mesa para tomar la comanda</span>
                            </div>
                        @else
                            <div class="text-xs font-bold text-slate-500 dark:text-[#c1c6d5]">Para Llevar — sin mesa</div>
                        @endif
                    </div>

                    {{-- Modalidad + Mozo + terminal --}}
                    <div class="flex items-center gap-3">
                        {{-- Toggle Comer Aquí / Para Llevar --}}
                        <div class="flex items-center gap-0.5 rounded-xl bg-slate-100 p-1 dark:bg-[#0b0e15]">
                            <button
                                wire:click="setOrderType('{{ \App\Filament\Pages\CrearPedido::TYPE_SALON }}')"
                                @disabled($tableLockedFromMap)
                                class="rounded-lg px-2.5 py-1.5 text-xs font-bold transition-colors {{ $this->orderType === \App\Filament\Pages\CrearPedido::TYPE_SALON ? 'bg-amber-500 text-white shadow-sm dark:bg-[#fbbc48] dark:text-[#422c00]' : 'text-slate-500 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:text-[#e0e2ec]' }}"
                            >Comer Aquí</button>
                            <button
                                wire:click="setOrderType('{{ \App\Filament\Pages\CrearPedido::TYPE_TAKEAWAY }}')"
                                @disabled($tableLockedFromMap)
                                class="rounded-lg px-2.5 py-1.5 text-xs font-bold transition-colors {{ $this->orderType === \App\Filament\Pages\CrearPedido::TYPE_TAKEAWAY ? 'bg-amber-500 text-white shadow-sm dark:bg-[#fbbc48] dark:text-[#422c00]' : 'text-slate-500 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:text-[#e0e2ec]' }} {{ $tableLockedFromMap ? 'opacity-40' : '' }}"
                            >Para Llevar</button>
                        </div>

                        {{-- Mozo --}}
                        <div class="hidden items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2 dark:bg-[#1c2026] lg:flex">
                            <x-heroicon-o-user-circle class="h-4 w-4 text-slate-500 dark:text-[#c1c6d5]" />
                            <span class="text-xs font-bold text-slate-900 dark:text-[#e0e2ec]">Mozo: <span class="font-semibold text-amber-600 dark:text-[#fbbc48]">{{ $this->mozoName }}</span></span>
                        </div>

                        {{-- Terminal links (solo los destinos que existen) --}}
                        <div class="hidden items-center gap-1 xl:flex">
                            <a href="{{ \App\Filament\Pages\TableMap::getUrl() }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]">Mapa de Mesas</a>
                            @if (\App\Filament\Resources\OrderResource\Pages\KitchenDashboard::canAccess())
                                <a href="{{ \App\Filament\Resources\OrderResource::getUrl('kitchen') }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]">Cocina KDS</a>
                            @endif
                            @if (\App\Filament\Resources\CajaResource::canViewAny())
                                <a href="{{ \App\Filament\Resources\CajaResource::getUrl('index') }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]">Caja / Cobro</a>
                            @endif
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-[1600px] px-4 py-4 sm:px-6">
                {{-- ══════════════ BARRA "MODO RÁPIDO MOZO" ══════════════ --}}
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white p-2.5 shadow-sm dark:bg-[#181c22]">
                    <div class="flex items-center gap-2.5">
                        <div class="flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-amber-600 dark:bg-[#272a31] dark:text-[#fbbc48]">
                            <x-heroicon-o-bolt class="h-4 w-4" />
                            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">Modo Rápido Mozo</span>
                        </div>
                        <div class="hidden h-6 w-px bg-slate-200 dark:bg-[#414753] sm:block"></div>
                        <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-[#c1c6d5]">
                            <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                            <span>Ticket en preparación: <strong class="font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $this->itemCount }} {{ $this->itemCount === 1 ? 'ítem' : 'ítems' }}</strong></span>
                            @if ($this->orderType === \App\Filament\Pages\CrearPedido::TYPE_SALON && $this->selectedTableId)
                                <span class="hidden md:inline">· Mesa <strong class="font-bold text-slate-900 dark:text-[#e0e2ec]">#{{ $this->selectedTable?->number }}</strong></span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button
                            wire:click="openTableModal"
                            @disabled($tableLockedFromMap)
                            class="flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-200 disabled:opacity-40 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]"
                            title="{{ $tableLockedFromMap ? 'Mesa bloqueada desde el Mapa de Mesas' : 'Cambiar la mesa de la comanda' }}"
                        >
                            <x-heroicon-o-table-cells class="h-4 w-4" />
                            @if ($tableLockedFromMap)
                                <x-heroicon-o-lock-closed class="h-3 w-3" />
                            @endif
                            <span>Cambiar Mesa</span>
                        </button>
                    </div>
                </div>

                {{-- ══════════════ SPLIT 70/30: CATÁLOGO + TICKET ══════════════ --}}
                <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-12">

                    {{-- ─────────── CATÁLOGO (70%) ─────────── --}}
                    <div class="flex min-w-0 flex-col gap-4 xl:col-span-8">

                        {{-- Carrusel de categorías --}}
                        <div class="flex items-center gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <button
                                wire:click="setCategory(null)"
                                class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold shadow-sm transition-all {{ $activeCategory === null ? 'bg-amber-500 text-white shadow-amber-500/20 dark:bg-[#fbbc48] dark:text-[#422c00]' : 'bg-white text-slate-700 hover:bg-slate-200 dark:bg-[#1c2026] dark:text-[#e0e2ec] dark:hover:bg-[#272a31]' }}"
                            >
                                <span>⭐</span>
                                <span>Todos</span>
                                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $activeCategory === null ? 'bg-white/20 text-inherit' : 'bg-slate-200 text-slate-600 dark:bg-[#272a31] dark:text-[#c1c6d5]' }}">{{ $this->totalAvailableCount }}</span>
                            </button>

                            @foreach ($this->categories as $category)
                                <button
                                    wire:click="setCategory({{ $category['id'] }})"
                                    class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-semibold shadow-sm transition-all {{ $activeCategory === $category['id'] ? 'bg-amber-500 text-white shadow-amber-500/20 dark:bg-[#fbbc48] dark:text-[#422c00]' : 'bg-white text-slate-700 hover:bg-slate-200 dark:bg-[#1c2026] dark:text-[#e0e2ec] dark:hover:bg-[#272a31]' }}"
                                >
                                    <span>{{ $category['emoji'] }}</span>
                                    <span>{{ $category['name'] }}</span>
                                    <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $activeCategory === $category['id'] ? 'bg-white/20 text-inherit' : 'bg-slate-200 text-slate-600 dark:bg-[#272a31] dark:text-[#c1c6d5]' }}">{{ $category['count'] }}</span>
                                </button>
                            @endforeach
                        </div>

                        {{-- Buscador + filtros --}}
                        <div class="grid grid-cols-1 gap-2 rounded-xl bg-white p-2.5 shadow-sm dark:bg-[#1c2026] md:grid-cols-12">
                            <div class="relative flex items-center md:col-span-8">
                                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 h-5 w-5 text-slate-400 dark:text-[#c1c6d5]" />
                                <input
                                    wire:model.live.debounce.300ms="search"
                                    type="text"
                                    placeholder="Buscar por nombre o código PLU (ej: #101)…"
                                    class="w-full rounded-lg border-0 bg-slate-100 py-2.5 pl-10 pr-10 text-sm text-slate-900 outline-none ring-0 placeholder:text-slate-400 focus:ring-1 focus:ring-amber-500 dark:bg-[#0b0e15] dark:text-[#e0e2ec] dark:placeholder:text-[#c1c6d5]/50"
                                />
                                @if ($this->search !== '')
                                    <button wire:click="$set('search', '')" class="absolute right-3 text-slate-400 transition-colors hover:text-slate-700 dark:hover:text-[#e0e2ec]" title="Limpiar búsqueda" type="button">
                                        <x-heroicon-o-x-mark class="h-4 w-4" />
                                    </button>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 overflow-x-auto md:col-span-4 md:justify-end">
                                {{-- Promo: solo hay respaldo real en is_temporal (ofertas anti-desperdicio).
                                     Los filtros dietéticos (Picante/Sin TACC/Veggie) no tienen campo real → NO se muestran. --}}
                                <button
                                    wire:click="togglePromo"
                                    class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $promoOnly ? 'bg-amber-500 text-white dark:bg-[#fbbc48] dark:text-[#422c00]' : 'bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 dark:bg-[#181c22] dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]' }}"
                                    title="Ofertas anti-desperdicio (productos temporales)"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                                    <span>Promo</span>
                                </button>
                            </div>
                        </div>

                        {{-- Grid táctil de productos --}}
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
                            @forelse ($this->catalogProducts as $product)
                                @php($stock = $this->stockMap[$product->id] ?? 0)
                                @php($outOfStock = $stock <= 0)
                                <div
                                    @if (! $outOfStock) wire:click="addItem({{ $product->id }})" @endif
                                    class="group relative flex cursor-pointer flex-col justify-between overflow-hidden rounded-xl bg-white shadow-sm transition-all duration-150 hover:bg-slate-50 active:scale-[0.98] dark:bg-[#1c2026] dark:hover:bg-[#272a31] {{ $outOfStock ? 'cursor-not-allowed opacity-60' : '' }}"
                                >
                                    {{-- Cabecera visual: gradiente + emoji de categoría (Product no tiene campo imagen) --}}
                                    <div class="relative flex h-24 w-full items-center justify-center bg-gradient-to-br from-amber-100 via-amber-50 to-slate-100 dark:from-[#32353c] dark:via-[#272a31] dark:to-[#181c22]">
                                        <span class="text-4xl drop-shadow-sm transition-transform duration-300 group-hover:scale-110">{{ \App\Filament\Pages\CrearPedido::categoryEmoji($product->category?->name ?? '') }}</span>

                                        @if (in_array($product->id, $this->topSellerIds, true))
                                            <span class="absolute left-2 top-2 flex items-center gap-1 rounded bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white shadow-md dark:bg-[#fbbc48] dark:text-[#422c00]">
                                                <x-heroicon-o-fire class="h-3 w-3" />
                                                Top Ventas
                                            </span>
                                        @endif

                                        @if ($product->preparation_time_minutes > 0)
                                            <span class="absolute bottom-2 right-2 rounded bg-white/85 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-700 backdrop-blur-md dark:bg-[#0b0e15]/85 dark:text-[#e0e2ec]">{{ $product->preparation_time_minutes }} min prep</span>
                                        @endif

                                        @if ($outOfStock)
                                            <span class="absolute bottom-2 left-2 flex items-center gap-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700 dark:bg-[#93000a] dark:text-[#ffdad6]">
                                                <x-heroicon-o-x-circle class="h-3 w-3" />
                                                Sin stock
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Cuerpo de la tarjeta --}}
                                    <div class="flex flex-grow flex-col p-3">
                                        <div class="flex items-start justify-between gap-1">
                                            <span class="line-clamp-1 text-sm font-bold text-slate-900 transition-colors group-hover:text-amber-600 dark:text-[#e0e2ec] dark:group-hover:text-[#fbbc48]">{{ $product->name }}</span>
                                        </div>
                                        @if ($product->description)
                                            <p class="mt-0.5 line-clamp-1 text-[11px] text-slate-500 dark:text-[#c1c6d5]">{{ $product->description }}</p>
                                        @endif
                                        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 dark:border-[#272a31]">
                                            <span class="text-lg font-bold text-amber-600 dark:text-[#fbbc48]">${{ \App\Filament\Pages\CrearPedido::money($product->price) }}</span>
                                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition-colors group-hover:bg-amber-500 group-hover:text-white dark:bg-[#32353c] dark:text-[#e0e2ec] dark:group-hover:bg-[#fbbc48] dark:group-hover:text-[#422c00]">
                                                <x-heroicon-o-plus class="h-4 w-4" />
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-span-full rounded-xl bg-white py-10 text-center text-sm text-slate-500 shadow-sm dark:bg-[#1c2026] dark:text-[#c1c6d5]">
                                    Sin productos que coincidan con la búsqueda o los filtros.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- ─────────── TICKET / COMANDA (30%) ─────────── --}}
                    <div class="relative flex min-w-0 flex-col rounded-2xl bg-white p-4 shadow-xl dark:bg-[#181c22] xl:col-span-4">

                        {{-- Header del ticket --}}
                        <div class="mb-3 rounded-xl bg-slate-50 p-3 dark:bg-[#1c2026]">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    @if ($this->orderType === \App\Filament\Pages\CrearPedido::TYPE_SALON && $this->selectedTableId)
                                        <span class="rounded bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-white dark:bg-[#fbbc48] dark:text-[#422c00]">MESA #{{ $this->selectedTable?->number }}</span>
                                        <span class="text-sm font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $this->selectedTable?->location }}</span>
                                    @elseif ($this->orderType === \App\Filament\Pages\CrearPedido::TYPE_SALON)
                                        <span class="rounded bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-700 dark:bg-[#93000a] dark:text-[#ffdad6]">SIN MESA</span>
                                        <span class="text-sm font-bold text-slate-900 dark:text-[#e0e2ec]">Elegí una mesa para continuar</span>
                                    @else
                                        <span class="rounded bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-white dark:bg-[#fbbc48] dark:text-[#422c00]">🥡 PARA LLEVAR</span>
                                        <span class="text-sm font-bold text-slate-900 dark:text-[#e0e2ec]">Comanda a retirar</span>
                                    @endif
                                </div>
                                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-amber-600 dark:bg-[#0b0e15] dark:text-[#fbbc48]">NUEVA</span>
                            </div>
                            <div class="flex items-center justify-between pt-1.5 text-[11px] text-slate-500 dark:text-[#c1c6d5]">
                                <span class="flex items-center gap-1"><x-heroicon-o-user-circle class="h-3.5 w-3.5" /> Mozo: {{ $this->mozoName }}</span>
                                <span class="flex items-center gap-1"><x-heroicon-o-shopping-bag class="h-3.5 w-3.5" /> {{ $this->itemCount }} {{ $this->itemCount === 1 ? 'unidad' : 'unidades' }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium dark:bg-[#272a31] dark:text-[#e0e2ec]">
                                    {{ $this->orderType === \App\Filament\Pages\CrearPedido::TYPE_SALON ? '🍽️ Salón' : '🥡 Para llevar' }}
                                </span>
                            </div>
                        </div>

                        {{-- Lista de ítems --}}
                        <div class="my-2 flex max-h-[440px] flex-col gap-2 overflow-y-auto pr-1 [scrollbar-width:thin]">
                            @forelse ($items as $index => $item)
                                <div class="flex flex-col gap-1.5 rounded-xl bg-slate-50 p-3 transition-all dark:bg-[#1c2026]">
                                    <div class="flex items-start justify-between">
                                        <div class="min-w-0 flex-grow pr-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono font-bold text-amber-600 dark:text-[#fbbc48]">{{ $item['qty'] }}x</span>
                                                <span class="text-sm font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $item['name'] }}</span>
                                            </div>
                                            @if (trim($item['note'] ?? '') !== '')
                                                <p class="mt-0.5 flex items-center gap-1 text-[11px] text-amber-600 dark:text-[#fbbc48]">
                                                    <x-heroicon-o-pencil-square class="h-3 w-3" />
                                                    {{ $item['note'] }}
                                                </p>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <span class="font-mono text-sm font-bold text-slate-900 dark:text-[#e0e2ec]">${{ \App\Filament\Pages\CrearPedido::money($item['price'] * $item['qty']) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between pt-1">
                                        <button wire:click="openNoteModal({{ $index }})" class="flex items-center gap-1 text-[11px] font-semibold text-slate-500 transition-colors hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:text-[#e0e2ec]">
                                            <x-heroicon-o-adjustments-horizontal class="h-3.5 w-3.5" /> {{ trim($item['note'] ?? '') !== '' ? 'Editar nota' : 'Nota' }}
                                        </button>
                                        <div class="flex items-center gap-1 rounded-lg bg-white p-0.5 dark:bg-[#0b0e15]">
                                            <button wire:click="decrementQty({{ $index }})" class="flex h-6 w-6 items-center justify-center rounded text-slate-500 transition-colors hover:bg-slate-200 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]" title="Reducir">
                                                <x-heroicon-o-minus class="h-3.5 w-3.5" />
                                            </button>
                                            <span class="w-5 text-center font-mono text-xs font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $item['qty'] }}</span>
                                            <button wire:click="incrementQty({{ $index }})" class="flex h-6 w-6 items-center justify-center rounded text-slate-500 transition-colors hover:bg-slate-200 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]" title="Aumentar">
                                                <x-heroicon-o-plus class="h-3.5 w-3.5" />
                                            </button>
                                            <button wire:click="removeItem({{ $index }})" class="ml-1 flex h-6 w-6 items-center justify-center rounded text-red-600 transition-colors hover:bg-red-50 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/20" title="Eliminar ítem">
                                                <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-200 py-8 text-center text-xs text-slate-400 dark:border-[#414753] dark:text-[#c1c6d5]/60">
                                    <x-heroicon-o-plus-circle class="mx-auto mb-2 h-8 w-8 opacity-60" />
                                    Tocá los productos del catálogo para armar la comanda
                                </div>
                            @endforelse
                        </div>

                        {{-- Nota a cocina (orders.notes) --}}
                        <div class="mb-2 rounded-xl bg-slate-50 p-2.5 dark:bg-[#1c2026]">
                            <label class="mb-1 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-[#c1c6d5]">
                                <x-heroicon-o-chat-bubble-oval-left-ellipsis class="h-3.5 w-3.5 text-amber-600 dark:text-[#fbbc48]" />
                                Nota a cocina
                            </label>
                            <textarea
                                wire:model="kitchenNote"
                                rows="1"
                                placeholder="Ej: hamburguesas término medio, salsa aparte…"
                                class="w-full resize-none rounded-lg border-0 bg-white p-2 text-xs text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-1 focus:ring-amber-500 dark:bg-[#0b0e15] dark:text-[#e0e2ec] dark:ring-[#414753]"
                            ></textarea>
                        </div>

                        {{-- Totales (el sistema NO calcula IVA por ítem → subtotal = total) --}}
                        <div class="mb-2 flex flex-col gap-1 rounded-xl bg-slate-50 p-3 dark:bg-[#1c2026]">
                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-[#c1c6d5]">
                                <span>{{ count($items) }} {{ count($items) === 1 ? 'producto' : 'productos' }} ({{ $this->itemCount }} {{ $this->itemCount === 1 ? 'unidad' : 'unidades' }})</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-[#e0e2ec]">${{ \App\Filament\Pages\CrearPedido::money($this->subtotal) }}</span>
                            </div>
                            <div class="my-1 h-px bg-slate-200 dark:bg-[#414753]/30"></div>
                            <div class="flex items-end justify-between">
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-[#c1c6d5]">TOTAL COMANDA</span>
                                    <div class="text-[10px] text-slate-400 dark:text-[#c1c6d5]/70">Subtotal = Total · IVA no calculado por ítem</div>
                                </div>
                                <span class="font-mono text-3xl font-bold tracking-tight text-amber-600 dark:text-[#fbbc48]">${{ \App\Filament\Pages\CrearPedido::money($this->total) }}</span>
                            </div>
                        </div>

                        {{-- CTA principal --}}
                        <div class="mt-1 flex flex-col gap-2">
                            <button
                                wire:click="submitOrder"
                                @disabled($this->itemCount === 0 || ($this->orderType === \App\Filament\Pages\CrearPedido::TYPE_SALON && ! $this->selectedTableId))
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 py-3.5 text-base font-bold text-white shadow-lg shadow-amber-500/20 transition-all hover:bg-amber-400 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]"
                                id="sendKdsBtn"
                            >
                                <x-heroicon-o-bolt class="h-6 w-6" />
                                ENVIAR A COCINA
                            </button>
                            <button wire:click="clearCart" class="mt-0.5 py-1 text-center text-xs font-semibold text-slate-400 transition-colors hover:text-red-600 dark:text-[#c1c6d5]/70 dark:hover:text-[#ffb4ab]">
                                Limpiar toda la comanda
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        {{-- ══════════════ MODAL: CAMBIAR MESA ══════════════ --}}
        @if ($showTableModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm dark:bg-[#0b0e15]/80" wire:click.self="showTableModal = false">
                <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl dark:bg-[#1c2026]">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-[#fbbc48]">Cambiar Mesa</span>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-[#e0e2ec]">¿A qué mesa va la comanda?</h3>
                        </div>
                        <button wire:click="showTableModal = false" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec]">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                    <select wire:model="newTableId" class="mt-4 w-full rounded-xl border-0 bg-slate-100 p-3 text-sm font-semibold text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-amber-500 dark:bg-[#0b0e15] dark:text-[#e0e2ec] dark:ring-[#414753]">
                        <option value="">Sin mesa (solo para llevar)</option>
                        @foreach ($this->tables as $table)
                            <option value="{{ $table['id'] }}">Mesa #{{ $table['number'] }} — {{ $table['location'] }} ({{ $table['status_label'] }})</option>
                        @endforeach
                    </select>
                    <div class="mt-4 flex gap-2">
                        <button wire:click="showTableModal = false" class="w-1/3 rounded-xl bg-slate-100 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec]">Cancelar</button>
                        <button wire:click="confirmTableChange" class="w-2/3 rounded-xl bg-amber-500 py-3 text-sm font-bold text-white transition-colors hover:bg-amber-400 dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]">Aplicar Mesa</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══════════════ MODAL: NOTA POR ÍTEM ══════════════ --}}
        @if ($editingItemIndex !== null && isset($items[$editingItemIndex]))
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm dark:bg-[#0b0e15]/80" wire:click.self="cancelNoteModal">
                <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl dark:bg-[#1c2026]">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-[#fbbc48]">Nota para cocina</span>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $items[$editingItemIndex]['name'] }}</h3>
                        </div>
                        <button wire:click="cancelNoteModal" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec]">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                    <textarea
                        wire:model="itemNote"
                        rows="3"
                        placeholder="Ej: sin cebolla, término medio, salsa aparte…"
                        class="mt-4 w-full rounded-xl border-0 bg-slate-100 p-3 text-sm text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-amber-500 dark:bg-[#0b0e15] dark:text-[#e0e2ec] dark:ring-[#414753]"
                    ></textarea>
                    <div class="mt-4 flex gap-2">
                        <button wire:click="cancelNoteModal" class="w-1/3 rounded-xl bg-slate-100 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec]">Cancelar</button>
                        <button wire:click="saveItemNote" class="w-2/3 flex items-center justify-center gap-1.5 rounded-xl bg-amber-500 py-3 text-sm font-bold text-white transition-colors hover:bg-amber-400 dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]">
                            <x-heroicon-o-check class="h-4 w-4" />
                            Aplicar
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>