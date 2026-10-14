<x-filament-panels::page>
    {{-- Gestión de Pedidos — lenguaje Stitch "Restaurante El Fogón": mismo clima
         que el KDS Cocina (fondo oscuro #10131a, tarjetas #1c2026, ámbar #fbbc48)
         pero orientado a la gestión del salón con auto-refresh cada 5s. --}}
    <div wire:poll.5s="loadOrders" class="space-y-6" style="font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;">
        @php
            $kpis = [
                [
                    'label' => 'PENDIENTES',
                    'value' => $stats['pending'],
                    'sub' => 'Esperando cocina',
                    'value_color' => 'text-amber-700 dark:text-[#fbbc48]',
                    'icon_bg' => 'bg-amber-100 border-amber-200 dark:bg-[#fbbc48]/10 dark:border-[#fbbc48]/25',
                    'icon_color' => 'text-amber-700 dark:text-[#fbbc48]',
                    'icon' => 'clock',
                ],
                [
                    'label' => 'EN PREPARACIÓN',
                    'value' => $stats['processing'],
                    'sub' => 'En cocina ahora',
                    'value_color' => 'text-sky-700 dark:text-sky-400',
                    'icon_bg' => 'bg-sky-100 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/25',
                    'icon_color' => 'text-sky-700 dark:text-sky-400',
                    'icon' => 'fire',
                ],
                [
                    'label' => 'LISTOS',
                    'value' => $stats['ready_for_pickup'],
                    'sub' => 'Para retirar',
                    'value_color' => 'text-green-700 dark:text-green-400',
                    'icon_bg' => 'bg-green-100 border-green-200 dark:bg-green-500/10 dark:border-green-500/25',
                    'icon_color' => 'text-green-700 dark:text-green-400',
                    'icon' => 'check',
                ],
                [
                    'label' => 'COMPLETADOS',
                    'value' => $stats['completed'],
                    'sub' => 'Servidos / cobrados',
                    'value_color' => 'text-emerald-700 dark:text-emerald-400',
                    'icon_bg' => 'bg-emerald-100 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/25',
                    'icon_color' => 'text-emerald-700 dark:text-emerald-400',
                    'icon' => 'check-circle',
                ],
                [
                    'label' => 'CANCELADOS',
                    'value' => $stats['cancelled'],
                    'sub' => 'Bajas del turno',
                    'value_color' => 'text-rose-700 dark:text-rose-400',
                    'icon_bg' => 'bg-rose-100 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/25',
                    'icon_color' => 'text-rose-700 dark:text-rose-400',
                    'icon' => 'x',
                ],
            ];
        @endphp

        {{-- ============ HEADER: marca El Fogón + reloj + accesos ============ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-[#272a31] dark:bg-[#181c22]">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-xl shadow-md shadow-amber-500/30 dark:bg-[#fbbc48]">
                        <span class="leading-none">🍽️</span>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-xl font-bold uppercase tracking-wider text-slate-900 dark:text-[#e0e2ec]">Restaurante El Fogón</span>
                        <span class="flex items-center gap-2 text-base font-semibold text-amber-600 dark:text-[#fbbc48]">
                            <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                            Gestión de Pedidos · En Servicio
                        </span>
                    </div>
                </div>

                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    {{-- Reloj en vivo --}}
                    <div class="hidden items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 dark:bg-[#0b0e15] md:flex">
                        <x-heroicon-o-clock class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" />
                        <div class="text-right leading-tight">
                            <span class="block font-mono text-xl font-black text-slate-900 dark:text-[#e0e2ec]">{{ now()->locale('es')->translatedFormat('H:i') }}</span>
                            <span class="block text-base font-semibold text-slate-500 dark:text-[#c1c6d5]">{{ now()->locale('es')->translatedFormat('l d/m') }} hs</span>
                        </div>
                    </div>

                    {{-- Accesos rápidos: Cocina (KDS) y Mapa de Mesas --}}
                    <div class="hidden items-center gap-1 xl:flex">
                        @if(auth()->user()?->hasAnyRole(['super_admin', 'Cocinero']))
                            <a href="{{ \App\Filament\Resources\OrderResource::getUrl('kitchen') }}" class="rounded-lg px-3 py-2 text-base font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]">🔥 Cocina</a>
                        @endif
                        @if(\App\Filament\Pages\TableMap::canAccess())
                            <a href="{{ \App\Filament\Pages\TableMap::getUrl() }}" class="rounded-lg px-3 py-2 text-base font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-[#c1c6d5] dark:hover:bg-[#272a31] dark:hover:text-[#e0e2ec]">Mapa de Mesas</a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Barra de filtros + búsqueda --}}
            <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-slate-200 pt-4 dark:border-[#272a31]">
                <div class="relative flex-1 sm:w-80">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Buscar por nº de pedido, cliente o mesa..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-100 py-3 pl-9 pr-3 text-base text-slate-700 placeholder-slate-400 transition-colors focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-[#414753] dark:bg-[#0f0f0f] dark:text-slate-200 dark:placeholder-slate-500"
                    />
                </div>

                <div class="flex flex-wrap rounded-lg border border-slate-300 bg-slate-100 p-1 text-sm dark:border-[#414753] dark:bg-[#0f0f0f]">
                    @foreach(['all' => 'Todos', 'pending' => 'Pendientes', 'processing' => 'En Preparación', 'ready_for_pickup' => 'Listos', 'completed' => 'Completados', 'cancelled' => 'Cancelados'] as $statusKey => $statusLabel)
                        <button
                            wire:click="$set('activeStatus', '{{ $statusKey }}')"
                            class="rounded-md px-3 py-1.5 transition-colors {{ $activeStatus === $statusKey ? 'bg-amber-500 font-bold text-white dark:bg-[#fbbc48] dark:text-[#422c00]' : 'font-medium text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200' }}"
                        >
                            {{ $statusLabel }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ============ KPIs (5) ============ --}}
        <section class="grid grid-cols-2 gap-4 sm:gap-4 md:grid-cols-3 xl:grid-cols-5" data-purpose="kpi-metrics-grid">
            @foreach($kpis as $kpi)
                <div class="relative flex items-center justify-between overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-md dark:border-[#272a31] dark:bg-[#181c22]">
                    <div class="z-10">
                        <p class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $kpi['label'] }}</p>
                        <p class="mt-1 text-4xl font-black {{ $kpi['value_color'] }}">{{ $kpi['value'] }}</p>
                        <p class="mt-0.5 text-base text-slate-500">{{ $kpi['sub'] }}</p>
                    </div>
                    <div class="flex h-14 w-14 items-center justify-center rounded-xl border {{ $kpi['icon_bg'] }} {{ $kpi['icon_color'] }}">
                        @if($kpi['icon'] === 'clock')
                            <x-heroicon-o-clock class="h-7 w-7" />
                        @elseif($kpi['icon'] === 'fire')
                            <x-heroicon-s-fire class="h-7 w-7" />
                        @elseif($kpi['icon'] === 'check')
                            <x-heroicon-o-check-badge class="h-7 w-7" />
                        @elseif($kpi['icon'] === 'check-circle')
                            <x-heroicon-o-check-circle class="h-7 w-7" />
                        @else
                            <x-heroicon-o-x-circle class="h-7 w-7" />
                        @endif
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ============ GRID DE TARJETAS DE PEDIDO ============ --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-[#272a31] dark:bg-[#181c22]" data-purpose="pedidos-grid">
            @php
                $visible = $this->visibleOrders;
            @endphp
            @if(count($visible) > 0)
                <div class="flex flex-col gap-3">
                    @foreach($visible as $orden)
                        @php
                            $badge = match ($orden['type']) {
                                'salon' => ['class' => 'bg-amber-500 text-white dark:bg-[#fbbc48] dark:text-[#422c00]', 'label' => 'MESA #' . ($orden['table_number'] ?? 'N/A')],
                                'para_llevar' => ['class' => 'bg-amber-100 text-amber-700 dark:bg-[#fbbc48]/15 dark:text-[#fbbc48]', 'label' => '🥡 PARA LLEVAR'],
                                'delivery' => ['class' => 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400', 'label' => '🛵 DELIVERY'],
                                default => ['class' => 'bg-slate-100 text-slate-600 dark:bg-[#272a31] dark:text-[#c1c6d5]', 'label' => '📄 PEDIDO'],
                            };
                            $statusBadge = match ($orden['status']) {
                                'pending' => ['class' => 'bg-amber-500/15 text-amber-700 border-amber-500/40 dark:text-[#fbbc48] dark:border-[#fbbc48]/40', 'dot' => 'bg-amber-500'],
                                'processing' => ['class' => 'bg-sky-500/15 text-sky-700 border-sky-500/40 dark:text-sky-400 dark:border-sky-500/40', 'dot' => 'bg-sky-500 animate-pulse'],
                                'ready_for_pickup' => ['class' => 'bg-green-500/15 text-green-700 border-green-500/40 dark:text-green-400 dark:border-green-500/40', 'dot' => 'bg-green-500'],
                                'completed' => ['class' => 'bg-emerald-500/15 text-emerald-700 border-emerald-500/40 dark:text-emerald-400 dark:border-emerald-500/40', 'dot' => 'bg-emerald-500'],
                                default => ['class' => 'bg-rose-500/15 text-rose-700 border-rose-500/40 dark:text-rose-400 dark:border-rose-500/40', 'dot' => 'bg-rose-500'],
                            };
                            $tieneSale = ! empty($orden['has_sale']);
                        @endphp
                        <article wire:key="pedido-{{ $orden['id'] }}" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-[#414753] dark:bg-[#1c2026] md:flex-row md:items-center md:gap-4">
                            {{-- Estado (columna izquierda: dot + hora) --}}
                            <div class="flex items-center justify-between gap-3 md:w-36 md:shrink-0 md:flex-col md:items-start md:gap-1">
                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-bold {{ $statusBadge['class'] }}">
                                    <span class="h-2 w-2 rounded-full {{ $statusBadge['dot'] }}"></span>
                                    {{ $orden['status_label'] }}
                                </span>
                                <span class="flex items-center gap-1.5 text-base font-semibold text-slate-500 dark:text-[#c1c6d5]">
                                    <x-heroicon-o-clock class="h-4 w-4 text-amber-600 dark:text-[#fbbc48]" />
                                    {{ $orden['created_at']->format('H:i') }} · {{ $orden['created_at']->locale('es')->diffForHumans(null, true) }}
                                </span>
                            </div>

                            {{-- Datos centrales --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="rounded-lg px-3 py-1.5 text-base font-black tracking-wide {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                    <span class="font-mono text-xl font-black text-slate-900 dark:text-[#e0e2ec]">#{{ $orden['id'] }}</span>
                                    <span class="text-base font-bold text-slate-900 dark:text-[#e0e2ec]">
                                        {{ $orden['type'] === 'salon' ? ($orden['table_number'] ? 'Mesa #'.$orden['table_number'] : 'Sin mesa') : ($orden['customer_name'] ?? 'Cliente') }}
                                    </span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-base text-slate-500 dark:text-[#c1c6d5]">
                                    <span class="flex items-center gap-1">
                                        <x-heroicon-o-user class="h-3.5 w-3.5" />
                                        {{ $orden['waiter_name'] }}
                                    </span>
                                    <span class="flex max-w-[70%] flex-wrap gap-x-3 gap-y-1">
                                        @forelse($orden['items'] as $item)
                                            <span class="flex items-center gap-1">
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-sm font-black text-slate-700 dark:bg-[#0b0e15] dark:text-[#e0e2ec]">{{ $item['quantity'] }}x</span>
                                                <span class="truncate font-semibold text-slate-700 dark:text-[#e0e2ec]">{{ $item['name'] }}</span>
                                            </span>
                                        @empty
                                            <span class="italic text-slate-400 dark:text-[#c1c6d5]/70">Sin ítems fuera de bebidas</span>
                                        @endforelse
                                    </span>
                                </div>
                                @if(trim($orden['notes']) !== '')
                                    <p class="mt-1 flex items-center gap-1 text-base font-semibold text-amber-600 dark:text-[#fbbc48]">
                                        <x-heroicon-o-bell-alert class="h-4 w-4 shrink-0" /> {{ $orden['notes'] }}
                                    </p>
                                @endif
                            </div>

                            {{-- Total + acciones (columna derecha) --}}
                            <div class="flex shrink-0 flex-col items-start gap-2 md:items-end md:w-56">
                                <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">$ {{ number_format((float) $orden['total'], 2, ',', '.') }}</span>
                                <div class="flex w-full flex-wrap items-center justify-start gap-2 md:justify-end">
                                    @if($orden['status'] === 'pending')
                                        <span
                                            class="inline-flex items-center gap-2 rounded-xl bg-amber-500/10 px-4 py-3 text-sm font-bold text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]"
                                            title="El cocinero inicia el pedido desde la pantalla de Cocina"
                                        >
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-amber-500"></span>
                                            ⏳ Esperando cocina
                                        </span>
                                        <button
                                            wire:click="cancelOrder({{ $orden['id'] }})"
                                            wire:confirm="¿Cancelar el pedido #{{ $orden['id'] }}? No se usaron ingredientes aún."
                                            class="flex items-center justify-center gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm font-bold text-red-700 transition-all hover:bg-red-100 dark:bg-[#93000a]/20 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/30"
                                        >
                                            ❌ Cancelar
                                        </button>
                                        <button
                                            wire:click="verPedido({{ $orden['id'] }})"
                                            class="flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]"
                                        >
                                            <x-heroicon-o-eye class="h-4 w-4" /> Ver
                                        </button>
                                    @elseif($orden['status'] === 'processing')
                                        <button
                                            wire:click="markAsReady({{ $orden['id'] }})"
                                            class="flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-amber-400 dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]"
                                        >
                                            ✅ LISTO
                                        </button>
                                        <button
                                            wire:click="markAsCompleted({{ $orden['id'] }})"
                                            class="flex items-center justify-center gap-2 rounded-xl bg-green-500 px-4 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-green-600 dark:bg-green-500 dark:text-[#052f1a] dark:hover:bg-green-400"
                                        >
                                            ✅ COMPLETADO
                                        </button>
                                        @if($this->puedeCobrar() && ! $tieneSale)
                                            <button
                                                wire:click="cobrarPedido({{ $orden['id'] }})"
                                                class="flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-emerald-400 dark:bg-emerald-500 dark:text-[#052f1a] dark:hover:bg-emerald-400"
                                            >
                                                <x-heroicon-o-credit-card class="h-4 w-4" /> Cobrar
                                            </button>
                                        @endif
                                        <button
                                            wire:click="cancelOrder({{ $orden['id'] }})"
                                            wire:confirm="⚠️ El pedido #{{ $orden['id'] }} está en preparación. Los ingredientes ya usados se registrarán como merma. ¿Confirmás la cancelación?"
                                            class="flex items-center justify-center gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm font-bold text-red-700 transition-all hover:bg-red-100 dark:bg-[#93000a]/20 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/30"
                                        >
                                            ❌ Cancelar
                                        </button>
                                        <button
                                            wire:click="verPedido({{ $orden['id'] }})"
                                            class="flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]"
                                        >
                                            <x-heroicon-o-eye class="h-4 w-4" /> Ver
                                        </button>
                                    @elseif($orden['status'] === 'ready_for_pickup')
                                        <button
                                            wire:click="markAsCompleted({{ $orden['id'] }})"
                                            class="flex items-center justify-center gap-2 rounded-xl bg-green-500 px-4 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-green-600 dark:bg-green-500 dark:text-[#052f1a] dark:hover:bg-green-400"
                                        >
                                            ✅ COMPLETADO
                                        </button>
                                        @if($this->puedeCobrar() && ! $tieneSale)
                                            <button
                                                wire:click="cobrarPedido({{ $orden['id'] }})"
                                                class="flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-emerald-400 dark:bg-emerald-500 dark:text-[#052f1a] dark:hover:bg-emerald-400"
                                            >
                                                <x-heroicon-o-credit-card class="h-4 w-4" /> Cobrar
                                            </button>
                                        @endif
                                        <button
                                            wire:click="verPedido({{ $orden['id'] }})"
                                            class="flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]"
                                        >
                                            <x-heroicon-o-eye class="h-4 w-4" /> Ver
                                        </button>
                                    @else
                                        <span class="rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-400 dark:bg-slate-900/40 dark:text-slate-500">
                                            {{ $orden['status_label'] }}
                                        </span>
                                        <button
                                            wire:click="verPedido({{ $orden['id'] }})"
                                            class="flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]"
                                        >
                                            <x-heroicon-o-eye class="h-4 w-4" /> Ver
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="py-14 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-amber-300 bg-amber-100 text-amber-600 dark:border-[#fbbc48]/20 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">
                        <x-heroicon-o-rectangle-stack class="h-8 w-8" />
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ count($this->orders) > 0 ? 'No hay pedidos con esos filtros' : 'No hay pedidos registrados' }}</h3>
                    <p class="mt-1 text-base text-slate-500 dark:text-slate-400">{{ count($this->orders) > 0 ? 'Ajustá la búsqueda o el filtro de estado.' : 'Los nuevos pedidos aparecen acá automáticamente.' }}</p>
                </div>
            @endif
        </section>

        {{-- Modal rápido de cobro reutilizado (trait HasCobroRapido, mismo Stitch del TPV/Mapa) --}}
        @include('filament.modals.cobro-cuenta-modal')

        {{-- ============ MODAL: DETALLE DEL PEDIDO (productos, Stitch) ============ --}}
        @if($pedidoModalId && $detalle = $this->detallePedido)
            @php
                $dBadge = match ($detalle['type']) {
                    'salon' => ['class' => 'bg-amber-500 text-white dark:bg-[#fbbc48] dark:text-[#422c00]', 'label' => 'MESA #' . ($detalle['table_number'] ?? 'N/A')],
                    'para_llevar' => ['class' => 'bg-amber-100 text-amber-700 dark:bg-[#fbbc48]/15 dark:text-[#fbbc48]', 'label' => '🥡 PARA LLEVAR'],
                    'delivery' => ['class' => 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400', 'label' => '🛵 DELIVERY'],
                    default => ['class' => 'bg-slate-100 text-slate-600 dark:bg-[#272a31] dark:text-[#c1c6d5]', 'label' => '📄 PEDIDO'],
                };
                $dStatus = match ($detalle['status']) {
                    'pending' => 'bg-amber-500/15 text-amber-700 border-amber-500/40 dark:text-[#fbbc48] dark:border-[#fbbc48]/40',
                    'processing' => 'bg-sky-500/15 text-sky-700 border-sky-500/40 dark:text-sky-400 dark:border-sky-500/40',
                    'ready_for_pickup' => 'bg-green-500/15 text-green-700 border-green-500/40 dark:text-green-400 dark:border-green-500/40',
                    'completed' => 'bg-emerald-500/15 text-emerald-700 border-emerald-500/40 dark:text-emerald-400 dark:border-emerald-500/40',
                    default => 'bg-rose-500/15 text-rose-700 border-rose-500/40 dark:text-rose-400 dark:border-rose-500/40',
                };
            @endphp
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm" wire:click.self="cerrarPedidoModal">
                <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl border border-amber-500/60 bg-white shadow-2xl dark:border-[#fbbc48]/40 dark:bg-[#0d0d0d]">
                    {{-- Header --}}
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 text-xl shadow-md shadow-amber-500/30 dark:bg-[#fbbc48]">
                                <span class="leading-none">🧾</span>
                            </div>
                            <div>
                                <span class="flex items-center gap-2 text-xl font-bold text-slate-900 dark:text-white">
                                    Pedido <span class="font-mono">#{{ $detalle['id'] }}</span>
                                </span>
                                <span class="text-base font-semibold text-slate-500 dark:text-slate-400">
                                    {{ $detalle['created_at']->format('d/m/Y H:i') }} · {{ $detalle['created_at']->locale('es')->diffForHumans(null, true) }}
                                </span>
                            </div>
                        </div>
                        <button wire:click="cerrarPedidoModal" class="p-1 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                    </div>

                    {{-- Cuerpo scrollable --}}
                    <div class="flex-1 overflow-y-auto px-6 py-4">
                        {{-- Badges --}}
                        <div class="mb-4 flex flex-wrap items-center gap-2">
                            <span class="rounded-lg px-3 py-1.5 text-base font-black tracking-wide {{ $dBadge['class'] }}">{{ $dBadge['label'] }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-bold {{ $dStatus }}">
                                {{ $detalle['status_label'] }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-500 dark:bg-[#0b0e15] dark:text-[#c1c6d5]">
                                <x-heroicon-o-user class="mr-1 inline h-3.5 w-3.5" />{{ $detalle['waiter_name'] }}
                            </span>
                        </div>

                        {{-- Cliente / mesa --}}
                        <div class="mb-4 rounded-xl bg-slate-50 p-3 text-base dark:bg-[#0b0e15]">
                            <span class="font-semibold text-slate-500 dark:text-[#c1c6d5]">{{ $detalle['type'] === 'salon' ? 'Mesa' : 'Cliente' }}: </span>
                            <span class="font-bold text-slate-900 dark:text-[#e0e2ec]">
                                {{ $detalle['type'] === 'salon' ? ($detalle['table_number'] ? 'Mesa #'.$detalle['table_number'] : 'Sin mesa') : ($detalle['customer_name'] ?? 'Cliente') }}
                            </span>
                        </div>

                        {{-- Productos (TODOS, incluye bebidas) --}}
                        <table class="w-full text-left text-base">
                            <thead>
                                <tr class="border-b border-slate-200 text-sm uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-[#c1c6d5]">
                                    <th class="py-2 pr-2 font-semibold">Producto</th>
                                    <th class="py-2 px-2 text-center font-semibold">Cant.</th>
                                    <th class="py-2 px-2 text-right font-semibold">Precio</th>
                                    <th class="py-2 pl-2 text-right font-semibold">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($detalle['items'] as $item)
                                    <tr class="border-b border-slate-100 dark:border-[#272a31]">
                                        <td class="py-2.5 pr-2 font-semibold text-slate-900 dark:text-[#e0e2ec]">
                                            {{ $item['name'] }}
                                            @if($item['category'] === 'Bebidas')
                                                <span class="ml-1 rounded-full bg-sky-100 px-1.5 py-0.5 text-sm font-bold text-sky-700 dark:bg-sky-500/15 dark:text-sky-400">BEBIDA</span>
                                            @endif
                                            @if($item['notes'] !== '')
                                                <span class="mt-0.5 block text-sm font-medium text-amber-600 dark:text-[#fbbc48]">📝 {{ $item['notes'] }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-2 text-center">
                                            <span class="inline-block min-w-8 rounded bg-slate-100 px-1.5 py-0.5 font-mono font-black text-slate-700 dark:bg-[#0b0e15] dark:text-[#e0e2ec]">{{ $item['quantity'] }}x</span>
                                        </td>
                                        <td class="py-2.5 px-2 text-right font-mono text-slate-500 dark:text-[#c1c6d5]">$ {{ number_format((float) $item['price'], 2, ',', '.') }}</td>
                                        <td class="py-2.5 pl-2 text-right font-bold text-slate-900 dark:text-[#e0e2ec]">$ {{ number_format((float) $item['subtotal'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if($detalle['notes'] !== '')
                            <p class="mt-3 flex items-center gap-1.5 rounded-xl bg-amber-50 px-3 py-2 text-base font-semibold text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">
                                <x-heroicon-o-bell-alert class="h-4 w-4 shrink-0" /> {{ $detalle['notes'] }}
                            </p>
                        @endif

                        {{-- Total --}}
                        <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-4 dark:border-slate-800">
                            <span class="text-sm uppercase tracking-wider font-bold text-slate-500 dark:text-[#c1c6d5]">Total a Pagar</span>
                            <span class="text-4xl font-black text-emerald-600 dark:text-emerald-400">$ {{ number_format((float) $detalle['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Acciones según estado (mismas reglas de negocio del listado) --}}
                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-6 py-4 dark:border-slate-800">
                        @if($detalle['status'] === 'pending')
                            <span class="inline-flex items-center gap-2 rounded-xl bg-amber-500/10 px-5 py-3 text-sm font-bold text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]" title="El cocinero inicia el pedido desde la pantalla de Cocina">
                                <span class="h-2 w-2 animate-pulse rounded-full bg-amber-500"></span>
                                ⏳ Esperando cocina
                            </span>
                            <button wire:click="cancelOrder({{ $detalle['id'] }})" wire:confirm="¿Cancelar el pedido #{{ $detalle['id'] }}? No se usaron ingredientes aún." class="inline-flex items-center gap-2 rounded-xl bg-red-50 px-5 py-3 text-sm font-bold text-red-700 transition-all hover:bg-red-100 dark:bg-[#93000a]/20 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/30">❌ Cancelar</button>
                        @elseif($detalle['status'] === 'processing')
                            <button wire:click="markAsReady({{ $detalle['id'] }})" class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-amber-400 dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]">✅ LISTO</button>
                            <button wire:click="markAsCompleted({{ $detalle['id'] }})" class="inline-flex items-center gap-2 rounded-xl bg-green-500 px-5 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-green-600 dark:bg-green-500 dark:text-[#052f1a] dark:hover:bg-green-400">✅ COMPLETADO</button>
                            @if($this->puedeCobrar() && ! $detalle['has_sale'])
                                <button wire:click="cobrarPedido({{ $detalle['id'] }})" class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-emerald-400 dark:bg-emerald-500 dark:text-[#052f1a] dark:hover:bg-emerald-400"><x-heroicon-o-credit-card class="h-4 w-4" /> Cobrar</button>
                            @endif
                            <button wire:click="cancelOrder({{ $detalle['id'] }})" wire:confirm="⚠️ El pedido #{{ $detalle['id'] }} está en preparación. Los ingredientes ya usados se registrarán como merma. ¿Confirmás la cancelación?" class="inline-flex items-center gap-2 rounded-xl bg-red-50 px-5 py-3 text-sm font-bold text-red-700 transition-all hover:bg-red-100 dark:bg-[#93000a]/20 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/30">❌ Cancelar</button>
                        @elseif($detalle['status'] === 'ready_for_pickup')
                            <button wire:click="markAsCompleted({{ $detalle['id'] }})" class="inline-flex items-center gap-2 rounded-xl bg-green-500 px-5 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-green-600 dark:bg-green-500 dark:text-[#052f1a] dark:hover:bg-green-400">✅ COMPLETADO</button>
                            @if($this->puedeCobrar() && ! $detalle['has_sale'])
                                <button wire:click="cobrarPedido({{ $detalle['id'] }})" class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-3 text-sm font-bold text-white shadow-sm transition-all hover:bg-emerald-400 dark:bg-emerald-500 dark:text-[#052f1a] dark:hover:bg-emerald-400"><x-heroicon-o-credit-card class="h-4 w-4" /> Cobrar</button>
                            @endif
                        @endif
                        <button wire:click="cerrarPedidoModal" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-200 dark:bg-[#0b0e15] dark:text-[#c1c6d5] dark:hover:bg-[#272a31]">Cerrar</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>