<x-filament-panels::page>
    {{-- TPV de Cobro — Cobrar Cuenta (estilo Stitch del POS, tokens de crear-pedido) --}}
    <div class="-m-6 -mt-6" style="font-family: 'Inter', 'Figtree', ui-sans-serif, system-ui, sans-serif;">
        <div class="min-h-screen bg-slate-100 text-[17px] text-slate-900 dark:bg-[#10131a] dark:text-[#e0e2ec] transition-colors">

            {{-- ══════════════ HEADER TPV ══════════════ --}}
            <header class="sticky top-0 z-30 border-b border-slate-200 dark:border-[#272a31] bg-white/90 backdrop-blur-md dark:bg-[#1c2026]/90">
                <div class="mx-auto flex max-w-none items-center justify-between gap-4 px-4 py-4 sm:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-md shadow-amber-500/30">
                            <x-heroicon-o-banknotes class="h-6 w-6" />
                        </div>
                        <div class="hidden min-w-0 flex-col sm:flex">
                            <span class="text-xl font-bold uppercase tracking-wider text-slate-900 dark:text-[#e0e2ec]">Cobrar Cuenta</span>
                            <span class="flex items-center gap-2 text-base font-semibold text-amber-600 dark:text-[#fbbc48]">
                                <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                                {{ $this->account ? 'Cuenta de mesa' : 'Seleccioná una mesa' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden items-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 dark:bg-[#1c2026] lg:flex">
                            <x-heroicon-o-user-circle class="h-5 w-5 text-slate-500 dark:text-[#c1c6d5]" />
                            <span class="text-base font-bold text-slate-900 dark:text-[#e0e2ec]">
                                {{ auth()->user()?->name ?? '—' }}
                                <span class="font-semibold text-amber-600 dark:text-[#fbbc48]">
                                    ({{ auth()->user()?->hasRole('Cajero') ? 'Cajero' : (auth()->user()?->hasRole('super_admin') ? 'Admin' : 'Mozo') }})
                                </span>
                            </span>
                        </div>
                        <div class="hidden items-center gap-1 xl:flex">
                            <a href="{{ \App\Filament\Pages\TableMap::getUrl() }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]">Mapa de Mesas</a>
                            <a href="{{ \App\Filament\Pages\CrearPedido::getUrl() }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]">Crear Pedido</a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-none px-4 py-4 sm:px-8">
                @if (! $this->account)
                    {{-- ══════════════ SELECTOR TÁCTIL DE MESAS CON CUENTA ══════════════ --}}
                    <div class="flex flex-col items-center gap-4 py-8">
                        <div class="flex items-center gap-2.5 rounded-xl bg-white px-5 py-3 shadow-sm dark:bg-[#181c22]">
                            <div class="flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-amber-600 dark:bg-[#272a31] dark:text-[#fbbc48]">
                                <x-heroicon-o-hand-raised class="h-5 w-5" />
                                <span class="text-sm font-bold uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">Elegí la mesa a cobrar</span>
                            </div>
                        </div>

                        @if ($this->chargeableTables->isNotEmpty())
                            <div class="grid w-full max-w-4xl grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                                @foreach ($this->chargeableTables as $table)
                                    <button
                                        wire:key="ct-{{ $table['id'] }}"
                                        wire:click="selectTable({{ $table['id'] }})"
                                        class="flex flex-col items-center gap-1.5 rounded-2xl border-2 border-transparent bg-white px-4 py-6 text-center shadow-sm transition-all hover:border-amber-500 hover:bg-slate-50 active:scale-[0.97] dark:bg-[#1c2026] dark:hover:border-[#fbbc48] dark:hover:bg-[#272a31]"
                                    >
                                        <span class="font-mono text-5xl font-black leading-none text-slate-900 dark:text-[#e0e2ec]">{{ $table['number'] }}</span>
                                        <span class="text-lg font-semibold text-slate-500 dark:text-[#c1c6d5]">{{ $table['zone_label'] }}</span>
                                        <span class="mt-1 flex items-center gap-1.5 rounded-full bg-amber-500/15 px-3 py-1 text-base font-bold text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">
                                            <x-heroicon-o-shopping-bag class="h-4 w-4" />
                                            {{ $table['orders_count'] }} {{ $table['orders_count'] === 1 ? 'comanda' : 'comandas' }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center dark:border-[#414753]">
                                <x-heroicon-o-check-circle class="mx-auto mb-2 h-12 w-12 text-emerald-500" />
                                <p class="text-lg font-bold text-slate-700 dark:text-[#e0e2ec]">No hay mesas ocupadas con cuenta pendiente</p>
                                <p class="mt-1 text-base text-slate-500 dark:text-[#c1c6d5]">Todas las cuentas están cobradas o no hay comandas activas.</p>
                            </div>
                        @endif

                        <a href="{{ \App\Filament\Pages\TableMap::getUrl() }}" class="mt-2 flex items-center gap-1.5 rounded-lg bg-slate-100 px-5 py-2.5 text-base font-semibold text-slate-600 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]">
                            <x-heroicon-o-arrow-left class="h-5 w-5" />
                            Volver al mapa
                        </a>
                    </div>
                @else
                    {{-- ══════════════ CUENTA: DETALLE (70%) + PAGO (30%) ══════════════ --}}
                    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-12">

                        {{-- ─────────── DETALLE DE LA CUENTA ─────────── --}}
                        <div class="flex min-w-0 flex-col gap-4 xl:col-span-7">
                            {{-- Header de la mesa --}}
                            <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-[#181c22]">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-lg bg-amber-500 px-4 py-2 font-mono text-2xl font-black text-white dark:bg-[#fbbc48] dark:text-[#422c00]">#{{ $this->account['number'] }}</span>
                                        <div>
                                            <div class="text-xl font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $this->account['zone_label'] }} · {{ $this->account['location'] }}</div>
                                            <div class="text-base text-slate-500 dark:text-[#c1c6d5]">{{ $this->account['status_label'] }} · Mesa {{ $this->account['number'] }}</div>
                                        </div>
                                    </div>
                                    <button
                                        wire:click="selectTable(null)"
                                        class="flex items-center gap-1.5 rounded-lg bg-slate-100 px-4 py-2.5 text-base font-semibold text-slate-600 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]"
                                        title="Elegir otra mesa para cobrar"
                                    >
                                        <x-heroicon-o-table-cells class="h-5 w-5" />
                                        Cambiar mesa
                                    </button>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-slate-100 pt-3 text-base text-slate-500 dark:border-[#272a31] dark:text-[#c1c6d5]">
                                    <span class="flex items-center gap-1.5"><x-heroicon-o-user-circle class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" /> Mozo: <strong class="font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $this->account['waiter_name'] }}</strong></span>
                                    <span class="flex items-center gap-1.5"><x-heroicon-o-shopping-bag class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" /> {{ $this->account['orders_count'] }} {{ $this->account['orders_count'] === 1 ? 'comanda' : 'comandas' }}</span>
                                    <span class="flex items-center gap-1.5"><x-heroicon-o-cube class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" /> {{ $this->account['items_count'] }} {{ $this->account['items_count'] === 1 ? 'ítem' : 'ítems' }}</span>
                                    <span class="flex items-center gap-1.5"><x-heroicon-o-clock class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" /> {{ $this->account['elapsed'] }}</span>
                                </div>
                            </div>

                            {{-- Comandas activas con sus ítems --}}
                            @foreach ($this->account['orders'] as $order)
                                <div wire:key="order-{{ $order['id'] }}" class="rounded-2xl bg-white p-4 shadow-sm dark:bg-[#1c2026]">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="rounded bg-slate-100 px-3 py-1 font-mono text-base font-bold text-amber-600 dark:bg-[#0b0e15] dark:text-[#fbbc48]">#{{ $order['id'] }}</span>
                                            <span class="text-lg font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $order['created_time'] }}</span>
                                            @if ($order['waiter_name'])
                                                <span class="hidden text-base text-slate-500 dark:text-[#c1c6d5] sm:inline">· {{ $order['waiter_name'] }}</span>
                                            @endif
                                        </div>
                                        <span class="font-mono text-xl font-bold text-slate-900 dark:text-[#e0e2ec]">${{ \App\Filament\Pages\CobrarCuenta::money($order['subtotal']) }}</span>
                                    </div>

                                    <div class="mt-2 flex flex-col gap-1.5">
                                        @foreach ($order['items'] as $item)
                                            <div class="flex items-start justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-[#181c22]">
                                                <div class="min-w-0 flex-grow">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xl leading-none">{{ $item['emoji'] }}</span>
                                                        <span class="font-mono text-lg font-bold text-amber-600 dark:text-[#fbbc48]">{{ $item['quantity'] }}x</span>
                                                        <span class="text-lg font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $item['name'] }}</span>
                                                    </div>
                                                    @if (trim((string) $item['notes']) !== '')
                                                        <p class="mt-0.5 flex items-center gap-1 pl-8 text-sm text-amber-600 dark:text-[#fbbc48]">
                                                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                                                            {{ $item['notes'] }}
                                                        </p>
                                                    @endif
                                                </div>
                                                <span class="font-mono text-lg font-bold text-slate-900 dark:text-[#e0e2ec]">${{ \App\Filament\Pages\CobrarCuenta::money($item['subtotal']) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- ─────────── PANEL DE COBRO (30%) ─────────── --}}
                        <div class="relative flex min-w-0 flex-col gap-4 rounded-2xl bg-white p-4 shadow-xl dark:bg-[#181c22] xl:col-span-5 xl:sticky xl:top-24">

                            {{-- Subtotal --}}
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#1c2026]">
                                <div class="flex items-center justify-between text-base text-slate-500 dark:text-[#c1c6d5]">
                                    <span>Subtotal ({{ $this->account['items_count'] }} {{ $this->account['items_count'] === 1 ? 'ítem' : 'ítems' }})</span>
                                    <span class="font-mono font-semibold text-slate-900 dark:text-[#e0e2ec]">${{ \App\Filament\Pages\CobrarCuenta::money($this->subtotal) }}</span>
                                </div>
                            </div>

                            {{-- Descuentos activos --}}
                            <div>
                                <label class="mb-2 flex items-center gap-1.5 text-base font-bold uppercase tracking-wider text-slate-500 dark:text-[#c1c6d5]">
                                    <x-heroicon-o-tag class="h-4 w-4 text-amber-600 dark:text-[#fbbc48]" />
                                    Descuentos (opcional)
                                </label>
                                @if ($this->activeDiscounts->isNotEmpty())
                                    <div class="flex max-h-40 flex-col gap-1.5 overflow-y-auto pr-1 [scrollbar-width:thin]">
                                        @foreach ($this->activeDiscounts as $discount)
                                            <label wire:key="disc-{{ $discount->id }}" class="flex cursor-pointer items-center gap-3 rounded-xl bg-slate-50 p-2.5 transition-colors hover:bg-slate-100 dark:bg-[#1c2026] dark:hover:bg-[#272a31]">
                                                <input
                                                    type="checkbox"
                                                    wire:model.live="selectedDiscounts"
                                                    value="{{ $discount->id }}"
                                                    class="h-5 w-5 rounded border-slate-300 text-amber-500 focus:ring-amber-500 dark:border-[#414753] dark:bg-[#0b0e15]"
                                                >
                                                <div class="min-w-0 flex-1">
                                                    <div class="truncate text-base font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $discount->name }}</div>
                                                    <div class="text-sm text-slate-500 dark:text-[#c1c6d5]">
                                                        {{ $discount->type === 'percentage' ? $discount->value.' %' : '$'.\App\Filament\Pages\CobrarCuenta::money($discount->value) }}
                                                    </div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="rounded-xl bg-slate-50 py-3 text-center text-base italic text-slate-400 dark:bg-[#1c2026] dark:text-[#c1c6d5]/70">
                                        No hay descuentos disponibles
                                    </div>
                                @endif

                                @if ($this->discountAmount > 0)
                                    <div class="mt-2 flex items-center justify-between rounded-lg bg-amber-500/10 px-3 py-2">
                                        <span class="text-base font-bold text-amber-700 dark:text-[#fbbc48]">Descuento</span>
                                        <span class="font-mono text-xl font-bold text-amber-700 dark:text-[#fbbc48]">-${{ \App\Filament\Pages\CobrarCuenta::money($this->discountAmount) }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Método de pago --}}
                            <div>
                                <label class="mb-2 flex items-center gap-1.5 text-base font-bold uppercase tracking-wider text-slate-500 dark:text-[#c1c6d5]">
                                    <x-heroicon-o-credit-card class="h-4 w-4 text-amber-600 dark:text-[#fbbc48]" />
                                    Método de pago
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach ([
                                        ['key' => 'cash', 'label' => 'Efectivo', 'icon' => 'heroicon-o-banknotes'],
                                        ['key' => 'card', 'label' => 'Tarjeta', 'icon' => 'heroicon-o-credit-card'],
                                        ['key' => 'transfer', 'label' => 'Transferencia', 'icon' => 'heroicon-o-arrows-right-left'],
                                    ] as $method)
                                        <button
                                            wire:key="pm-{{ $method['key'] }}"
                                            wire:click="$set('paymentMethod', '{{ $method['key'] }}')"
                                            class="flex flex-col items-center justify-center gap-1 rounded-xl border-2 px-2 py-4 text-base font-bold transition-all active:scale-[0.97] {{ $this->paymentMethod === $method['key'] ? 'border-amber-500 bg-amber-500 text-white shadow-lg shadow-amber-500/20 dark:border-[#fbbc48] dark:bg-[#fbbc48] dark:text-[#422c00]' : 'border-transparent bg-slate-50 text-slate-600 hover:border-amber-500/50 dark:bg-[#1c2026] dark:text-[#c1c6d5] dark:hover:border-[#fbbc48]/50' }}"
                                        >
                                            <x-dynamic-component :component="$method['icon']" class="h-6 w-6" />
                                            {{ $method['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Efectivo: monto recibido + vuelto en vivo --}}
                            @if ($this->paymentMethod === 'cash')
                                <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#1c2026]">
                                    <label class="mb-1.5 flex items-center gap-1.5 text-base font-bold uppercase tracking-wider text-slate-500 dark:text-[#c1c6d5]">
                                        <x-heroicon-o-banknotes class="h-4 w-4 text-amber-600 dark:text-[#fbbc48]" />
                                        Monto recibido
                                    </label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-mono text-2xl font-black text-amber-600 dark:text-[#fbbc48]">$</span>
                                        <input
                                            wire:model.live="montoRecibido"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0"
                                            class="w-full rounded-xl border-0 bg-white py-4 pl-10 pr-4 text-right font-mono text-3xl font-black text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-amber-500 dark:bg-[#0b0e15] dark:text-[#e0e2ec] dark:ring-[#414753]"
                                        >
                                    </div>

                                    @if ($this->cashInsufficient())
                                        <div class="mt-2 flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-base font-semibold text-red-700 dark:bg-[#93000a]/20 dark:text-[#ffb4ab]">
                                            <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                                            Falta dinero: el recibido es menor al total.
                                        </div>
                                    @endif

                                    <div class="mt-3 flex items-center justify-between rounded-lg bg-amber-500/10 px-3 py-2">
                                        <span class="text-base font-bold text-amber-700 dark:text-[#fbbc48]">Vuelto</span>
                                        <span class="font-mono text-2xl font-black text-amber-700 dark:text-[#fbbc48]">${{ \App\Filament\Pages\CobrarCuenta::money($this->vuelto) }}</span>
                                    </div>
                                </div>
                            @endif

                            {{-- TOTAL --}}
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#1c2026]">
                                <div class="my-0.5 h-px bg-slate-200 dark:bg-[#414753]/40"></div>
                                <div class="flex items-end justify-between pt-1">
                                    <div>
                                        <span class="text-base font-bold uppercase tracking-wider text-slate-500 dark:text-[#c1c6d5]">TOTAL A COBRAR</span>
                                        <div class="text-sm text-slate-400 dark:text-[#c1c6d5]/70">{{ $this->paymentMethodLabel() }}</div>
                                    </div>
                                    <span class="font-mono text-5xl font-black tracking-tight text-amber-600 dark:text-[#fbbc48]">${{ \App\Filament\Pages\CobrarCuenta::money($this->total) }}</span>
                                </div>
                            </div>

                            {{-- Acciones --}}
                            <div class="mt-1 flex flex-col gap-2">
                                <button
                                    wire:click="cobrarCuenta"
                                    @disabled(! $this->canCobrar())
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 py-5 text-xl font-bold text-white shadow-lg shadow-amber-500/20 transition-all hover:bg-amber-400 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]"
                                >
                                    <x-heroicon-o-check-circle class="h-7 w-7" />
                                    COBRAR CUENTA
                                </button>
                                <div class="flex items-center justify-between px-1">
                                    <button wire:click="selectTable(null)" class="py-1.5 text-base font-semibold text-slate-400 transition-colors hover:text-red-600 dark:text-[#c1c6d5]/70 dark:hover:text-[#ffb4ab]">
                                        Limpiar / cambiar mesa
                                    </button>
                                    <a href="{{ \App\Filament\Pages\TableMap::getUrl() }}" class="flex items-center gap-1 py-1.5 text-base font-semibold text-slate-400 transition-colors hover:text-amber-600 dark:text-[#c1c6d5]/70 dark:hover:text-[#fbbc48]">
                                        <x-heroicon-o-map class="h-4 w-4" />
                                        Volver al mapa
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </main>
        </div>
    </div>
</x-filament-panels::page>