<x-filament-panels::page x-data="{ openNewTable: false, openTableActionModal: false }">
    {{-- Mapa de Mesas — diseño Stitch (fondo negro puro + paleta ámbar) --}}
    <div class="space-y-6" style="font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;">
        @php
            $selected = $this->selectedTable;
            $discounts = \App\Models\Discount::where('is_active', true)->get();
            $kpis = [
                [
                    'label' => 'DISPONIBLES',
                    'value' => $stats['available'],
                    'sub' => 'Listas para asignar',
                    'value_color' => 'text-amber-600 dark:text-amber-400',
                    'card' => 'border-slate-200 dark:border-slate-800/80',
                    'icon' => 'check',
                    'icon_color' => 'text-amber-600 dark:text-amber-400',
                    'icon_bg' => 'bg-amber-100 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20',
                ],
                [
                    'label' => 'OCUPADAS',
                    'value' => $stats['occupied'],
                    'sub' => $stats['avg_stay'] !== null ? 'Promedio: '.$stats['avg_stay'].' min' : 'Sin permanencia registrada',
                    'value_color' => 'text-rose-600 dark:text-rose-400',
                    'card' => 'border-slate-200 dark:border-slate-800/80',
                    'icon' => 'fire',
                    'icon_color' => 'text-rose-600 dark:text-rose-400',
                    'icon_bg' => 'bg-rose-100 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20',
                ],
                [
                    'label' => 'RESERVADAS',
                    'value' => $stats['reserved'],
                    'sub' => $stats['next_turn'] ? 'Próx. turno '.$stats['next_turn'].'h' : 'Sin próximos turnos',
                    'value_color' => 'text-amber-600 dark:text-amber-400',
                    'card' => 'border-amber-200 dark:border-amber-500/30',
                    'icon' => 'clock',
                    'icon_color' => 'text-amber-600 dark:text-amber-400',
                    'icon_bg' => 'bg-amber-100 border-amber-200 dark:bg-amber-500/20 dark:border-amber-500/40',
                ],
                [
                    'label' => 'POR COBRAR',
                    'value' => $stats['por_cobrar'],
                    'sub' => 'Pendiente: $'.number_format($stats['por_cobrar_total'], 2),
                    'value_color' => 'text-cyan-600 dark:text-cyan-400',
                    'card' => 'border-cyan-200 dark:border-cyan-500/30',
                    'icon' => 'receipt',
                    'icon_color' => 'text-cyan-600 dark:text-cyan-400',
                    'icon_bg' => 'bg-cyan-100 border-cyan-200 dark:bg-cyan-500/10 dark:border-cyan-500/20',
                ],
            ];
        @endphp

        {{-- ============ HEADER: título, badge, buscador, filtros y acciones ============ --}}
        <div class="bg-white dark:bg-[#0d0d0d] rounded-2xl border border-slate-200 dark:border-slate-800/80 p-6 shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Mapa de Mesas</h1>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 border border-emerald-300 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                            En Directo
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 hidden md:block">Servicio Noche / Cena • Plano Arquitectónico Digital</p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto">
                    {{-- Buscador --}}
                    <div class="relative flex-1 sm:w-80">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="text"
                            placeholder="Buscar mesa o comensales..."
                            class="w-full bg-slate-100 dark:bg-[#0f0f0f] text-sm rounded-lg pl-9 pr-3 py-3 border border-slate-300 dark:border-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors"
                        />
                    </div>

                    {{-- Filtros por zona (segmented) --}}
                    <div class="flex bg-slate-100 dark:bg-[#0f0f0f] p-1 rounded-lg border border-slate-300 dark:border-slate-800 text-xs">
                        @foreach(['all' => 'Todas', 'terraza' => 'Terraza', 'salon' => 'Salón', 'barra' => 'Barra'] as $zoneKey => $zoneLabel)
                            <button
                                wire:click="$set('activeZone', '{{ $zoneKey }}')"
                                class="px-3 py-1.5 rounded-md {{ $activeZone === $zoneKey ? 'bg-amber-500 text-white font-bold' : 'font-medium text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200' }} transition-colors"
                            >
                                {{ $zoneLabel }}
                            </button>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-1.5 ml-auto lg:ml-0">
                        {{-- Nueva Mesa --}}
                        <button
                            x-on:click="openNewTable = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-white rounded-lg text-xs font-bold shadow-md transition-all"
                        >
                            <x-heroicon-o-plus class="w-3.5 h-3.5 stroke-[2.5]" />
                            <span>Nueva Mesa</span>
                        </button>
                        {{-- Fullscreen --}}
                        <button
                            x-on:click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()"
                            class="hidden sm:inline-flex p-2 rounded-lg bg-slate-100 dark:bg-[#0f0f0f] border border-slate-300 dark:border-slate-800 hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors"
                            title="Pantalla Completa"
                        >
                            <x-heroicon-o-arrows-pointing-out class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ KPIs (4) ============ --}}
        <section class="grid grid-cols-2 md:grid-cols-4 gap-3.5 sm:gap-4" data-purpose="kpi-metrics-grid">
            @foreach($kpis as $kpi)
                <div class="bg-white dark:bg-[#0d0d0d] rounded-xl p-6 border {{ $kpi['card'] }} shadow-md relative overflow-hidden flex items-center justify-between">
                    <div class="z-10">
                        <p class="text-xs sm:text-sm tracking-wider uppercase font-semibold text-slate-500 dark:text-slate-400">{{ $kpi['label'] }}</p>
                        <p class="text-4xl sm:text-5xl font-black {{ $kpi['value_color'] }} mt-1">{{ $kpi['value'] }}</p>
                        <p class="text-sm text-slate-500 mt-0.5">{{ $kpi['sub'] }}</p>
                    </div>
                    <div class="w-16 h-16 rounded-xl {{ $kpi['icon_bg'] }} flex items-center justify-center {{ $kpi['icon_color'] }}">
                        @if($kpi['icon'] === 'check')
                            <x-heroicon-o-check-circle class="w-8 h-8" />
                        @elseif($kpi['icon'] === 'fire')
                            <x-heroicon-s-fire class="w-8 h-8" />
                        @elseif($kpi['icon'] === 'clock')
                            <x-heroicon-o-clock class="w-8 h-8" />
                        @else
                            <x-heroicon-o-receipt-percent class="w-8 h-8" />
                        @endif
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ============ PLANO POR ZONAS (70%) + PANEL DERECHO (30%) ============ --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- COLUMNA IZQUIERDA: PLANO --}}
            <section class="lg:col-span-7 flex flex-col gap-6" data-purpose="table-floor-plan">
                @forelse($this->visibleZones as $zoneKey => $locationTables)
                    {{-- El filtro por zona lo resuelve TableMap::visibleZones(): acá NO hay
                         @continue, el plano siempre itera la misma colección (morph estable). --}}

                    {{-- Bloque de zona --}}
                    <article wire:key="zone-{{ $zoneKey }}" class="zone-block bg-white dark:bg-[#080808] border border-slate-200 dark:border-slate-800/80 rounded-2xl p-6 shadow-lg relative" data-zone-id="{{ $zoneKey }}">
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800/80 pb-3 mb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-amber-100 border border-amber-300 dark:bg-amber-500/15 dark:border-amber-500/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
                                    @if($zoneKey === 'terraza')
                                        <x-heroicon-o-sun class="w-4 h-4" />
                                    @elseif($zoneKey === 'barra')
                                        <x-heroicon-o-beaker class="w-4 h-4" />
                                    @else
                                        <x-heroicon-o-building-storefront class="w-4 h-4" />
                                    @endif
                                </div>
                                <h2 class="text-xl font-extrabold tracking-wider text-slate-900 dark:text-slate-100 uppercase">{{ \App\Filament\Pages\TableMap::ZONE_LABELS[$zoneKey] }}</h2>
                            </div>
                            <span class="px-3 py-1 bg-amber-500 text-white font-bold text-sm rounded-lg tracking-wide">
                                {{ count($locationTables) }} {{ $zoneKey === 'barra' ? 'puestos' : 'mesas' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                            @foreach($locationTables as $table)
                                @php
                                    $sc = match ($table['status']) {
                                        'occupied' => 'border-rose-500 bg-rose-50 border dark:border-rose-500/50 dark:bg-rose-500/10',
                                        'reserved' => 'border-amber-500 bg-amber-50 border dark:border-amber-500/60 dark:bg-amber-500/15',
                                        'maintenance' => 'border-slate-300 bg-slate-100 border border-dashed dark:border-slate-700 dark:bg-slate-900/40',
                                        default => 'border-slate-300 bg-white border dark:border-slate-800 dark:bg-[#0f0f0f]',
                                    };
                                    $round = $zoneKey === 'barra' ? 'rounded-full' : 'rounded-xl';
                                    $matches = $this->tableMatchesSearch($table);
                                @endphp
                                <button
                                    wire:key="table-{{ $table['id'] }}"
                                    wire:click="selectTable({{ $table['id'] }})"
                                    class="mesa-card text-left relative rounded-xl p-3 flex flex-col justify-between items-center min-h-[220px] {{ $sc }} transition-all duration-200 hover:-translate-y-0.5 {{ $selectedTableId === $table['id'] ? 'ring-2 ring-amber-500 shadow-[0_0_20px_rgba(245,158,11,0.35)]' : '' }} {{ $matches ? '' : 'opacity-25' }}"
                                    title="Mesa {{ $table['number'] }} — {{ $table['zone_label'] }}"
                                >
                                    @if($table['status'] === 'occupied' && $table['orders_count'] > 0)
                                        <span class="badge-counter absolute -top-2 -right-2 w-6 h-6 rounded-full bg-rose-500 text-white font-black text-xs flex items-center justify-center border-2 border-white dark:border-[#080808]">{{ $table['orders_count'] }}</span>
                                    @endif

                                    {{-- Forma de la mesa: redondeada con 4 sillas / barra circular con 1 silla --}}
                                    <div class="relative w-28 h-28 my-1 flex items-center justify-center">
                                        <div class="{{ $round }} absolute inset-2 border-2 {{ $table['status'] === 'occupied' ? 'border-rose-500/60 bg-rose-500/20' : ($table['status'] === 'reserved' ? 'border-amber-500/70 bg-amber-500/20' : 'border-amber-500/40 bg-slate-100 dark:bg-slate-900/60') }} flex items-center justify-center">
                                            <span class="text-4xl font-black {{ $table['status'] === 'occupied' ? 'text-rose-600 dark:text-rose-300' : ($table['status'] === 'reserved' ? 'text-amber-700 dark:text-amber-300' : 'text-slate-900 dark:text-white') }}">{{ $table['number'] }}</span>
                                        </div>
                                        @if($zoneKey === 'barra')
                                            <span class="absolute top-0 left-1/2 -translate-x-1/2 w-3.5 h-3.5 rounded-full {{ $table['status'] === 'occupied' ? 'bg-rose-500 dark:bg-rose-400' : 'bg-amber-500 dark:bg-amber-400' }}"></span>
                                        @else
                                            <span class="absolute top-0 left-0 w-3.5 h-3.5 rounded-full {{ $table['status'] === 'occupied' ? 'bg-rose-500 dark:bg-rose-400' : 'bg-amber-500 dark:bg-amber-400' }}"></span>
                                            <span class="absolute top-0 right-0 w-3.5 h-3.5 rounded-full {{ $table['status'] === 'occupied' ? 'bg-rose-500 dark:bg-rose-400' : 'bg-amber-500 dark:bg-amber-400' }}"></span>
                                            <span class="absolute bottom-0 left-0 w-3.5 h-3.5 rounded-full {{ $table['status'] === 'occupied' ? 'bg-rose-500 dark:bg-rose-400' : 'bg-amber-500 dark:bg-amber-400' }}"></span>
                                            <span class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full {{ $table['status'] === 'occupied' ? 'bg-rose-500 dark:bg-rose-400' : 'bg-amber-500 dark:bg-amber-400' }}"></span>
                                        @endif
                                    </div>

                                    {{-- Comensales según estado REAL: disponible → capacidad, ocupada → pedidos
                                            activos, reservada → próxima reserva, mantenimiento → Mantenimiento --}}
                                    <span class="text-sm {{ $table['status'] === 'occupied' ? 'font-bold text-rose-600 dark:text-rose-300' : ($table['status'] === 'reserved' ? 'font-bold text-amber-700 dark:text-amber-300' : 'font-semibold text-slate-500 dark:text-slate-400') }}">{{ $this->paxLabel($table) }}</span>

                                    @if($table['status'] === 'occupied' && $table['orders_count'] > 0)
                                        <span class="text-xs text-rose-600 dark:text-rose-300/90 flex items-center gap-1 mt-0.5">
                                            <x-heroicon-o-document-text class="w-3 h-3" />
                                            #{{ $table['first_order_id'] }} · {{ $table['elapsed_time'] }}
                                        </span>
                                    @elseif($table['status'] === 'reserved' && $table['has_reservation'])
                                        <span class="text-xs text-amber-700 dark:text-amber-300/90 truncate w-full text-center mt-0.5">{{ $table['reservation_info'] }}</span>
                                    @endif

                                    {{-- Botón de estado --}}
                                    <span class="w-full text-center py-1.5 rounded {{ $table['status'] === 'occupied' ? 'bg-rose-500 text-white dark:bg-rose-500/90' : ($table['status'] === 'reserved' ? 'bg-amber-100 text-amber-700 border border-amber-300 dark:bg-amber-500/30 dark:text-amber-300 dark:border-amber-500/40' : 'bg-amber-500 hover:bg-amber-400 text-white') }} text-xs font-black uppercase tracking-wider flex items-center justify-center gap-1 mt-1 shadow-sm transition-colors">
                                        @if($table['status'] === 'occupied')
                                            <x-heroicon-s-fire class="w-3 h-3" /> OCUPADA
                                        @elseif($table['status'] === 'reserved')
                                            <x-heroicon-o-clock class="w-3 h-3" /> RESERVADA
                                        @elseif($table['status'] === 'maintenance')
                                            <x-heroicon-o-wrench-screwdriver class="w-3 h-3" /> MANTENIMIENTO
                                        @else
                                            <x-heroicon-o-check-circle class="w-3 h-3" /> DISPONIBLE
                                        @endif
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </article>
                @empty
                    @php($hasAnyTable = collect($tablesByLocation)->flatten(1)->isNotEmpty())
                    <div class="bg-white dark:bg-[#0d0d0d] rounded-2xl border border-slate-200 dark:border-slate-800/80 p-12 text-center">
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">{{ $hasAnyTable ? 'No hay mesas en esta zona' : 'No hay mesas registradas' }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $hasAnyTable ? 'Cambiá el filtro de zona para ver otras mesas.' : 'Creá una mesa para empezar a gestionar tu salón.' }}</p>
                    </div>
                @endforelse
            </section>

            {{-- COLUMNA DERECHA: PANEL DE DETALLE (30%, sticky) --}}
            <aside class="lg:col-span-5 bg-white dark:bg-[#0d0d0d] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-2xl sticky top-20" data-purpose="table-detail-panel">
                @if($selected)
                    {{-- Cabecera del panel --}}
                    <div class="flex items-start justify-between border-b border-slate-200 dark:border-slate-800 pb-4 mb-4">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs uppercase font-bold tracking-wider text-emerald-600 dark:text-emerald-400">{{ $selected['zone_label'] }}</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                                <span class="text-xs text-cyan-600 dark:text-cyan-400 font-mono">#M-{{ $selected['number'] }}</span>
                            </div>
                            <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1 flex items-center gap-2">
                                <span>Mesa</span>
                                <span class="text-amber-600 dark:text-amber-400 text-4xl font-black">{{ $selected['number'] }}</span>
                            </h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-sm font-bold {{ $selected['status'] === 'occupied' ? 'bg-rose-100 text-rose-700 border border-rose-300 dark:bg-rose-500/20 dark:text-rose-300 dark:border-rose-500/40' : ($selected['status'] === 'reserved' ? 'bg-amber-100 text-amber-700 border border-amber-300 dark:bg-amber-500/20 dark:text-amber-300 dark:border-amber-500/40' : 'bg-emerald-100 text-emerald-700 border border-emerald-300 dark:bg-emerald-500/20 dark:text-emerald-300 dark:border-emerald-500/40') }} flex items-center gap-1.5 shrink-0">
                            <span class="w-2 h-2 rounded-full {{ $selected['status'] === 'occupied' ? 'bg-rose-500 animate-pulse' : ($selected['status'] === 'reserved' ? 'bg-amber-500 dark:bg-amber-400' : 'bg-emerald-500 dark:bg-emerald-400') }}"></span>
                            {{ $selected['status_label'] }}
                        </span>
                    </div>

                    {{-- Ficha de datos operativos --}}
                    <div class="space-y-3 bg-slate-50 dark:bg-[#0f0f0f] p-3.5 rounded-xl border border-slate-200 dark:border-slate-800/90 text-sm">
                        <div class="flex justify-between items-center text-slate-700 dark:text-slate-300">
                            <span class="text-slate-500 dark:text-slate-400">Camarero Asignado:</span>
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $selected['waiter_name'] }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-700 dark:text-slate-300">
                            <span class="text-slate-500 dark:text-slate-400">Permanencia:</span>
                            <span class="font-semibold text-amber-600 dark:text-amber-400">{{ $selected['permanence'] ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-700 dark:text-slate-300">
                            <span class="text-slate-500 dark:text-slate-400">Nº Comanda:</span>
                            <span class="font-mono text-cyan-600 dark:text-cyan-400 font-bold">{{ $selected['first_order_id'] ? '#'.$selected['first_order_id'] : 'Sin Comanda' }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-700 dark:text-slate-300">
                            <span class="text-slate-500 dark:text-slate-400">Capacidad Máx:</span>
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $selected['capacity'] }} {{ $selected['capacity'] === 1 ? 'Comensal' : 'Comensales' }}</span>
                        </div>
                        @if($selected['has_reservation'])
                            <div class="flex justify-between items-center text-slate-700 dark:text-slate-300 border-t border-slate-200 dark:border-slate-800 pt-3">
                                <span class="text-slate-500 dark:text-slate-400">Próxima Reserva:</span>
                                <span class="font-semibold text-amber-600 dark:text-amber-300">{{ $selected['reservation_info'] }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Comanda en curso --}}
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                                Comanda en Curso
                            </span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">
                                {{ collect($selected['orders'])->sum(fn ($o) => count($o['products'])) }} artículos
                            </span>
                        </div>

                        @php($orderItems = collect($selected['orders'])->flatMap(fn ($o) => collect($o['products'])->map(fn ($p) => $p + ['order_id' => $o['id']])))
                        @if($orderItems->isNotEmpty())
                            <div class="space-y-2 max-h-44 overflow-y-auto pr-1" id="order-items-list">
                                @foreach($orderItems as $item)
                                    <div class="flex items-center justify-between bg-slate-50 dark:bg-[#070707] p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 text-sm">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-5 h-5 rounded bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400 font-bold flex items-center justify-center text-[10px] shrink-0">{{ $item['quantity'] }}x</span>
                                            <span class="text-slate-700 dark:text-slate-200 truncate">{{ $item['name'] }}</span>
                                        </div>
                                        <span class="font-semibold text-slate-900 dark:text-slate-100 shrink-0 ml-2">${{ number_format($item['quantity'] * $item['price'], 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="py-6 text-center text-slate-500 dark:text-slate-500 text-sm bg-slate-50 dark:bg-[#070707] rounded-lg border border-dashed border-slate-300 dark:border-slate-800">
                                Mesa lista y sin consumición activa
                            </div>
                        @endif

                        {{-- Total a pagar --}}
                        <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-800 flex justify-between items-baseline">
                            <span class="text-xs uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400">Total a Pagar</span>
                            <span class="text-4xl font-black text-emerald-600 dark:text-emerald-400">${{ number_format($selected['total_amount'], 2) }}</span>
                        </div>
                    </div>

                    {{-- Botones de acción primaria --}}
                    <div class="mt-5 space-y-2">
                        <button
                            wire:click="{{ $selected['first_order_id'] ? 'editOrder('.$selected['first_order_id'].')' : 'createOrderForTable' }}"
                            class="w-full py-4 bg-amber-500 hover:bg-amber-400 active:scale-[0.99] text-white font-black rounded-xl text-sm transition-all flex items-center justify-center gap-2 shadow-lg shadow-amber-500/20"
                            title="{{ $selected['first_order_id'] ? 'Gestionar el pedido activo de la mesa' : 'Crear un pedido nuevo para la mesa' }}"
                        >
                            @if($selected['first_order_id'])
                                <x-heroicon-o-pencil-square class="w-4 h-4 stroke-[2.5]" />
                            @else
                                <x-heroicon-o-plus class="w-4 h-4 stroke-[2.5]" />
                            @endif
                            <span>Crear Pedido</span>
                        </button>
                        <button
                            wire:click="prepareCobroMesa({{ $selected['id'] }})"
                            x-on:click="$dispatch('open-modal', { id: 'cobrar-mesa' })"
                            @disabled($selected['orders_count'] === 0)
                            class="w-full py-2.5 {{ $selected['orders_count'] === 0 ? 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed' : 'bg-emerald-500 hover:bg-emerald-400 text-white' }} active:scale-[0.99] font-bold rounded-xl text-xs transition-all flex items-center justify-center gap-2 shadow-md"
                            @if($selected['orders_count'] === 0) title="No hay pedidos activos para cobrar" @endif
                        >
                            <x-heroicon-o-credit-card class="w-4 h-4" />
                            <span>Cobrar Cuenta / Imprimir Factura</span>
                        </button>
                        @if(in_array($selected['status'], ['available', 'occupied'], true))
                            <button
                                wire:click="createReservation"
                                class="w-full py-2.5 bg-transparent border border-amber-500/70 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 active:scale-[0.99] font-bold rounded-xl text-xs transition-all flex items-center justify-center gap-2"
                            >
                                <x-heroicon-o-calendar class="w-4 h-4" />
                                <span>Reservar</span>
                            </button>
                        @endif
                    </div>

                    {{-- Acciones rápidas secundarias --}}
                    <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-800 grid grid-cols-2 gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                        <button
                            wire:click="openTableAction('move')"
                            x-on:click="openTableActionModal = true"
                            @disabled($selected['status'] !== 'occupied' || $selected['orders_count'] === 0)
                            class="p-3.5 rounded-lg bg-slate-50 dark:bg-[#0f0f0f] border border-slate-200 dark:border-slate-800 text-center flex items-center justify-center gap-1.5 transition-colors {{ $selected['status'] === 'occupied' && $selected['orders_count'] > 0 ? 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' : 'text-slate-400 dark:text-slate-500 cursor-not-allowed' }}"
                            @if($selected['status'] !== 'occupied' || $selected['orders_count'] === 0) title="La mesa debe estar ocupada con pedidos activos" @endif
                        >
                            <x-heroicon-o-arrows-right-left class="w-3.5 h-3.5 text-cyan-600 dark:text-cyan-400" />
                            Cambiar Mesa
                        </button>
                        <button
                            wire:click="openTableAction('merge')"
                            x-on:click="openTableActionModal = true"
                            @disabled($selected['status'] !== 'occupied' || $selected['orders_count'] === 0)
                            class="p-3.5 rounded-lg bg-slate-50 dark:bg-[#0f0f0f] border border-slate-200 dark:border-slate-800 text-center flex items-center justify-center gap-1.5 transition-colors {{ $selected['status'] === 'occupied' && $selected['orders_count'] > 0 ? 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' : 'text-slate-400 dark:text-slate-500 cursor-not-allowed' }}"
                            @if($selected['status'] !== 'occupied' || $selected['orders_count'] === 0) title="La mesa debe estar ocupada con pedidos activos" @endif
                        >
                            <x-heroicon-o-plus class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                            Unir Mesas
                        </button>
                        @if($this->isSuperAdmin())
                            <button
                                wire:click="toggleTableStatus({{ $selected['id'] }})"
                                class="p-3.5 rounded-lg bg-slate-50 dark:bg-[#0f0f0f] border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white text-center flex items-center justify-center gap-1.5 transition-colors"
                            >
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                                Cambiar Estado
                            </button>
                        @else
                            <button disabled title="Solo super_admin puede cambiar el estado" class="p-3.5 rounded-lg bg-slate-50 dark:bg-[#0f0f0f] border border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed text-center flex items-center justify-center gap-1.5">
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                                Cambiar Estado
                            </button>
                        @endif
                        <button
                            wire:click="freeTable({{ $selected['id'] }})"
                            wire:confirm="¿Liberar la mesa {{ $selected['number'] }}?"
                            class="p-3.5 rounded-lg bg-slate-50 dark:bg-[#0f0f0f] border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white text-center flex items-center justify-center gap-1.5 transition-colors text-rose-600 dark:text-rose-400"
                        >
                            <x-heroicon-o-trash class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                            Liberar Mesa
                        </button>
                    </div>
                @else
                    {{-- Estado vacío amigable --}}
                    <div class="py-14 text-center">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 border border-amber-300 dark:bg-amber-500/10 dark:border-amber-500/20 flex items-center justify-center text-amber-600 dark:text-amber-400 mb-4">
                            <x-heroicon-o-map class="w-8 h-8" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Seleccioná una mesa para ver sus detalles</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Tocá cualquier mesa del plano para ver sus especificaciones, comanda en curso y acciones de gestión.</p>
                    </div>
                @endif
            </aside>
        </div>

        {{-- ============ MODAL: AÑADIR NUEVA MESA ============ --}}
        <div x-show="openNewTable" x-cloak class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div x-on:click.outside="openNewTable = false" class="bg-white dark:bg-[#0d0d0d] border border-amber-500/60 dark:border-amber-500/40 rounded-2xl w-full max-w-lg p-6 shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        Añadir Nueva Mesa al Plano
                    </h3>
                    <button x-on:click="openNewTable = false" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white p-1">✕</button>
                </div>

                <form wire:submit="createTable" class="mt-4 space-y-4">
                    <div>
                        <label for="new-number" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Número o Código de Mesa</label>
                        <input
                            id="new-number"
                            type="number"
                            wire:model="newNumber"
                            min="1"
                            placeholder="Ej: 50"
                            class="w-full bg-white dark:bg-[#111111] border rounded-lg p-3.5 text-slate-900 dark:text-white text-sm placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 focus:outline-none {{ $errors->has('newNumber') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                        />
                        @error('newNumber')
                            <p class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="new-zone" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Zona Asignada</label>
                        <select
                            id="new-zone"
                            wire:model="newLocation"
                            class="w-full bg-white dark:bg-[#111111] border rounded-lg p-3.5 text-slate-900 dark:text-white text-sm focus:border-amber-500 focus:outline-none {{ $errors->has('newLocation') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                        >
                            @foreach(\App\Filament\Pages\TableMap::ZONE_LABELS as $zoneKey => $zoneLabel)
                                <option value="{{ $zoneKey }}">{{ $zoneLabel }}</option>
                            @endforeach
                        </select>
                        @error('newLocation')
                            <p class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="new-capacity" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Capacidad Comensales (Pax)</label>
                        <input
                            id="new-capacity"
                            type="number"
                            wire:model="newCapacity"
                            min="1"
                            max="20"
                            class="w-full bg-white dark:bg-[#111111] border rounded-lg p-3.5 text-slate-900 dark:text-white text-sm placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 focus:outline-none {{ $errors->has('newCapacity') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                        />
                        @error('newCapacity')
                            <p class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="pt-3 flex gap-2">
                        <button type="button" x-on:click="openNewTable = false" class="flex-1 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition-colors">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" class="flex-1 py-2.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-white text-xs font-black transition-colors">Guardar Mesa</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ============ MODAL: CAMBIAR MESA / UNIR MESAS ============ --}}
        @if($selected)
        <div x-show="openTableActionModal" x-cloak class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div x-on:click.outside="openTableActionModal = false" class="bg-white dark:bg-[#0d0d0d] border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ $tableAction === 'merge' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        {{ $tableAction === 'merge' ? 'Unir Mesas' : 'Cambiar Mesa' }}
                    </h3>
                    <button x-on:click="openTableActionModal = false" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white p-1">✕</button>
                </div>

                <div class="mt-4 space-y-4">
                    @if($tableAction === 'merge')
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Se moverán <strong class="text-slate-700 dark:text-slate-200">todos los pedidos activos</strong> de la mesa elegida a la mesa
                            <strong class="text-amber-600 dark:text-amber-400">#{{ $selected['number'] }}</strong>, y la mesa elegida quedará libre.
                        </p>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Los pedidos activos de la mesa <strong class="text-amber-600 dark:text-amber-400">#{{ $selected['number'] }}</strong>
                            se moverán a una mesa <strong class="text-slate-700 dark:text-slate-200">disponible</strong>.
                        </p>
                    @endif

                    <div>
                        <label for="target-table" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $tableAction === 'merge' ? 'Mesa a unir' : 'Mesa destino' }}
                        </label>
                        <select
                            id="target-table"
                            wire:model="targetTableId"
                            class="w-full bg-white dark:bg-[#111111] border rounded-lg p-3.5 text-slate-900 dark:text-white text-sm focus:border-amber-500 focus:outline-none border-slate-300 dark:border-slate-700"
                        >
                            <option value="">— Elegí una mesa —</option>
                            @forelse($this->targetTableOptions as $option)
                                <option value="{{ $option['id'] }}">
                                    Mesa {{ $option['number'] }} · {{ $tableAction === 'merge' ? $option['active_orders_count'].' pedidos' : $option['capacity'].' pax' }}
                                </option>
                            @empty
                                <option value="" disabled>No hay mesas {{ $tableAction === 'merge' ? 'ocupadas con pedidos' : 'disponibles' }}</option>
                            @endforelse
                        </select>
                    </div>

                    <div class="pt-2 flex gap-2">
                        <button type="button" x-on:click="openTableActionModal = false" class="flex-1 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition-colors">Cancelar</button>
                        <button
                            type="button"
                            wire:click="{{ $tableAction === 'merge' ? 'mergeOrdersIntoTable' : 'moveOrdersToTable' }}"
                            x-on:click="openTableActionModal = false"
                            @disabled(! $targetTableId)
                            class="flex-1 py-2.5 rounded-lg {{ $targetTableId ? 'bg-amber-500 hover:bg-amber-400 text-white font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed' }} text-xs transition-colors"
                        >
                            {{ $tableAction === 'merge' ? 'Unir Mesas' : 'Confirmar Cambio' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ============ TOAST DE FEEDBACK (Alpine) ============ --}}
        <div
            x-data="{ show: false, msg: '' }"
            x-on:table-selected.window="msg = $event.detail.message; show = true; clearTimeout(window.__moodiToast); window.__moodiToast = setTimeout(() => show = false, 2500)"
            class="fixed bottom-5 right-5 z-[60] bg-white dark:bg-[#111111] border border-amber-500 text-amber-600 dark:text-amber-400 px-4 py-2.5 rounded-xl shadow-2xl text-xs font-semibold flex items-center gap-2 transition-all duration-300"
            :class="show ? 'translate-y-0 opacity-100' : 'translate-y-20 opacity-0'"
        >
            <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
            <span x-text="msg">Mesa seleccionada</span>
        </div>

        {{-- ============ MODAL DE COBRO (tema del sistema) ============ --}}
        <x-filament::modal id="cobrar-mesa" width="2xl">
            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    <div class="bg-amber-500 rounded-xl p-3">
                        <x-heroicon-o-currency-dollar class="w-8 h-8 text-white" />
                    </div>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white">Cobrar Mesa</h3>
                </div>
            </x-slot>

            <div class="space-y-6">
                {{-- Total sin descuento --}}
                <div class="bg-slate-50 dark:bg-[#0f0f0f] rounded-lg p-6 border border-slate-200 dark:border-slate-800">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-slate-700 dark:text-slate-300">Subtotal</span>
                        <span class="text-3xl font-black text-slate-900 dark:text-white">
                            ${{ number_format($totalAmount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Método de Pago --}}
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-3">
                        💳 Método de Pago
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <button
                            wire:click="$set('paymentMethod', 'cash')"
                            class="flex flex-col items-center justify-center p-4 rounded-lg border font-bold transition-all
                                {{ $paymentMethod === 'cash' ? 'bg-amber-500 border-amber-600 text-white shadow-lg shadow-amber-500/20' : 'bg-white dark:bg-[#0f0f0f] border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-amber-500' }}">
                            <x-heroicon-o-banknotes class="w-8 h-8 mb-2" />
                            Efectivo
                        </button>
                        <button
                            wire:click="$set('paymentMethod', 'card')"
                            class="flex flex-col items-center justify-center p-4 rounded-lg border font-bold transition-all
                                {{ $paymentMethod === 'card' ? 'bg-amber-500 border-amber-600 text-white shadow-lg shadow-amber-500/20' : 'bg-white dark:bg-[#0f0f0f] border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-amber-500' }}">
                            <x-heroicon-o-credit-card class="w-8 h-8 mb-2" />
                            Tarjeta
                        </button>
                        <button
                            wire:click="$set('paymentMethod', 'transfer')"
                            class="flex flex-col items-center justify-center p-4 rounded-lg border font-bold transition-all
                                {{ $paymentMethod === 'transfer' ? 'bg-amber-500 border-amber-600 text-white shadow-lg shadow-amber-500/20' : 'bg-white dark:bg-[#0f0f0f] border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-amber-500' }}">
                            <x-heroicon-o-arrows-right-left class="w-8 h-8 mb-2" />
                            Transferencia
                        </button>
                    </div>
                </div>

                {{-- Descuentos --}}
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-3">
                        🏷️ Aplicar Descuentos (Opcional)
                    </label>
                    @if($discounts->count() > 0)
                        <div class="space-y-2 max-h-48 overflow-y-auto bg-white dark:bg-[#0f0f0f] rounded-lg border border-slate-200 dark:border-slate-800 p-3">
                            @foreach($discounts as $discount)
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer border border-transparent hover:border-amber-500/50 transition-all">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedDiscounts"
                                        value="{{ $discount->id }}"
                                        class="w-5 h-5 text-amber-500 border-slate-300 dark:border-slate-600 dark:bg-slate-900 rounded focus:ring-amber-500">
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900 dark:text-white">{{ $discount->name }}</div>
                                        <div class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ $discount->type === 'percentage' ? $discount->value . '%' : '$' . number_format($discount->value, 0, ',', '.') }} de descuento
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-slate-500 italic">
                            No hay descuentos disponibles
                        </div>
                    @endif
                </div>

                {{-- Descuento aplicado --}}
                @if($discountAmount > 0)
                    <div class="bg-amber-50 dark:bg-amber-500/10 rounded-lg p-4 border border-amber-500/40">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-amber-800 dark:text-amber-300">Descuento Total</span>
                            <span class="text-2xl font-black text-amber-600 dark:text-amber-400">
                                -${{ number_format($discountAmount, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @endif

                {{-- Total final --}}
                <div class="bg-[#0d0d0d] rounded-lg p-6 border border-amber-500/60">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-lg font-bold text-amber-400 mb-1">TOTAL A COBRAR</p>
                            <p class="text-sm text-amber-400/75">{{ ucfirst($paymentMethod === 'cash' ? 'Efectivo' : ($paymentMethod === 'card' ? 'Tarjeta' : 'Transferencia')) }}</p>
                        </div>
                        <span class="text-5xl font-black text-amber-400">
                            ${{ number_format(max(0, $totalAmount - $discountAmount), 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                @if ($selectedTableId)
                    <div class="text-center">
                        <a
                            href="{{ \App\Filament\Pages\CobrarCuenta::getUrl(['table_id' => $selectedTableId]) }}"
                            class="inline-flex items-center gap-1.5 text-sm font-bold text-amber-600 dark:text-amber-400 hover:underline"
                        >
                            <x-heroicon-o-banknotes class="w-4 h-4" />
                            Abrir TPV de Cuenta (descuentos + vuelto)
                        </a>
                    </div>
                @endif
            </div>

            <x-slot name="footerActions">
                <div class="flex gap-3 w-full">
                    <button
                        x-on:click="$dispatch('close-modal', { id: 'cobrar-mesa' })"
                        class="flex-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-black py-3 px-6 rounded-lg text-lg">
                        Cancelar
                    </button>
                    <button
                        wire:click="cobrarMesa"
                        wire:loading.attr="disabled"
                        class="flex-1 bg-amber-500 hover:bg-amber-400 text-white font-black py-3 px-6 rounded-lg border border-amber-600 shadow-lg shadow-amber-500/20 text-lg flex items-center justify-center gap-2">
                        <x-heroicon-o-check-circle class="w-6 h-6" />
                        <span wire:loading.remove>Confirmar Cobro</span>
                        <span wire:loading>Procesando...</span>
                    </button>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>