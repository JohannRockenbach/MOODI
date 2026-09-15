<x-filament-panels::page x-data="{ openNewTable: false }">
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
                    'value_color' => 'text-amber-400',
                    'card' => 'border-slate-800/80',
                    'icon' => 'check',
                    'icon_color' => 'text-amber-400',
                    'icon_bg' => 'bg-amber-500/10 border-amber-500/20',
                ],
                [
                    'label' => 'OCUPADAS',
                    'value' => $stats['occupied'],
                    'sub' => $stats['avg_stay'] !== null ? 'Promedio: '.$stats['avg_stay'].' min' : 'Sin permanencia registrada',
                    'value_color' => 'text-rose-400',
                    'card' => 'border-slate-800/80',
                    'icon' => 'fire',
                    'icon_color' => 'text-rose-400',
                    'icon_bg' => 'bg-rose-500/10 border-rose-500/20',
                ],
                [
                    'label' => 'RESERVADAS',
                    'value' => $stats['reserved'],
                    'sub' => $stats['next_turn'] ? 'Próx. turno '.$stats['next_turn'].'h' : 'Sin próximos turnos',
                    'value_color' => 'text-amber-400',
                    'card' => 'border-amber-500/30',
                    'icon' => 'clock',
                    'icon_color' => 'text-amber-400',
                    'icon_bg' => 'bg-amber-500/20 border-amber-500/40',
                ],
                [
                    'label' => 'POR COBRAR',
                    'value' => $stats['por_cobrar'],
                    'sub' => 'Pendiente: $'.number_format($stats['por_cobrar_total'], 2),
                    'value_color' => 'text-cyan-400',
                    'card' => 'border-cyan-500/30',
                    'icon' => 'receipt',
                    'icon_color' => 'text-cyan-400',
                    'icon_bg' => 'bg-cyan-500/10 border-cyan-500/20',
                ],
            ];
        @endphp

        {{-- ============ HEADER: título, badge, buscador, filtros y acciones ============ --}}
        <div class="bg-[#0d0d0d] rounded-2xl border border-slate-800/80 p-6 shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-white">Mapa de Mesas</h1>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            En Directo
                        </span>
                    </div>
                    <p class="text-sm text-slate-400 hidden md:block">Servicio Noche / Cena • Plano Arquitectónico Digital</p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto">
                    {{-- Buscador --}}
                    <div class="relative flex-1 sm:w-80">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="text"
                            placeholder="Buscar mesa o comensales..."
                            class="w-full bg-[#0f0f0f] text-sm rounded-lg pl-9 pr-3 py-3 border border-slate-800 text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors"
                        />
                    </div>

                    {{-- Filtros por zona (segmented) --}}
                    <div class="flex bg-[#0f0f0f] p-1 rounded-lg border border-slate-800 text-xs">
                        @foreach(['all' => 'Todas', 'terraza' => 'Terraza', 'salon' => 'Salón', 'barra' => 'Barra'] as $zoneKey => $zoneLabel)
                            <button
                                wire:click="$set('activeZone', '{{ $zoneKey }}')"
                                class="px-3 py-1.5 rounded-md {{ $activeZone === $zoneKey ? 'bg-amber-500 text-slate-950 font-bold' : 'font-medium text-slate-400 hover:text-slate-200' }} transition-colors"
                            >
                                {{ $zoneLabel }}
                            </button>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-1.5 ml-auto lg:ml-0">
                        {{-- Nueva Mesa --}}
                        <button
                            x-on:click="openNewTable = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-lg text-xs font-bold shadow-md transition-all"
                        >
                            <x-heroicon-o-plus class="w-3.5 h-3.5 stroke-[2.5]" />
                            <span>Nueva Mesa</span>
                        </button>
                        {{-- Fullscreen --}}
                        <button
                            x-on:click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()"
                            class="hidden sm:inline-flex p-2 rounded-lg bg-[#0f0f0f] border border-slate-800 hover:bg-slate-800 text-slate-300 hover:text-white transition-colors"
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
                <div class="bg-[#0d0d0d] rounded-xl p-6 border {{ $kpi['card'] }} shadow-md relative overflow-hidden flex items-center justify-between">
                    <div class="z-10">
                        <p class="text-xs sm:text-sm tracking-wider uppercase font-semibold text-slate-400">{{ $kpi['label'] }}</p>
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
                @forelse($tablesByLocation as $zoneKey => $locationTables)
                    @if(count($locationTables) === 0 || ($activeZone !== 'all' && $activeZone !== $zoneKey))
                        @continue
                    @endif

                    {{-- Bloque de zona --}}
                    <article wire:key="zone-{{ $zoneKey }}" class="zone-block bg-[#080808] border border-slate-800/80 rounded-2xl p-6 shadow-lg relative" data-zone-id="{{ $zoneKey }}">
                        <div class="flex items-center justify-between border-b border-slate-800/80 pb-3 mb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400">
                                    @if($zoneKey === 'terraza')
                                        <x-heroicon-o-sun class="w-4 h-4" />
                                    @elseif($zoneKey === 'barra')
                                        <x-heroicon-o-beaker class="w-4 h-4" />
                                    @else
                                        <x-heroicon-o-building-storefront class="w-4 h-4" />
                                    @endif
                                </div>
                                <h2 class="text-xl font-extrabold tracking-wider text-slate-100 uppercase">{{ \App\Filament\Pages\TableMap::ZONE_LABELS[$zoneKey] }}</h2>
                            </div>
                            <span class="px-3 py-1 bg-amber-500 text-slate-950 font-bold text-sm rounded-lg tracking-wide">
                                {{ count($locationTables) }} {{ $zoneKey === 'barra' ? 'puestos' : 'mesas' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                            @foreach($locationTables as $table)
                                @php
                                    $sc = match ($table['status']) {
                                        'occupied' => 'border-rose-500/50 bg-rose-500/10 border',
                                        'reserved' => 'border-amber-500/60 bg-amber-500/15 border',
                                        'maintenance' => 'border-slate-700 bg-slate-900/40 border border-dashed',
                                        default => 'border-slate-800 bg-[#0f0f0f] border',
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
                                        <span class="badge-counter absolute -top-2 -right-2 w-6 h-6 rounded-full bg-rose-500 text-white font-black text-xs flex items-center justify-center border-2 border-[#080808]">{{ $table['orders_count'] }}</span>
                                    @endif

                                    {{-- Forma de la mesa: redondeada con 4 sillas / barra circular con 1 silla --}}
                                    <div class="relative w-28 h-28 my-1 flex items-center justify-center">
                                        <div class="{{ $round }} absolute inset-2 border-2 {{ $table['status'] === 'occupied' ? 'border-rose-500/60 bg-rose-500/20' : ($table['status'] === 'reserved' ? 'border-amber-500/70 bg-amber-500/20' : 'border-amber-500/40 bg-slate-900/60') }} flex items-center justify-center">
                                            <span class="text-4xl font-black {{ $table['status'] === 'occupied' ? 'text-rose-300' : ($table['status'] === 'reserved' ? 'text-amber-300' : 'text-white') }}">{{ $table['number'] }}</span>
                                        </div>
                                        @if($zoneKey === 'barra')
                                            <span class="absolute top-0 left-1/2 -translate-x-1/2 w-3.5 h-3.5 rounded-full {{ $table['status'] === 'occupied' ? 'bg-rose-400' : 'bg-amber-400' }}"></span>
                                        @else
                                            <span class="absolute top-0 left-0 w-3.5 h-3.5 rounded-full {{ $table['status'] === 'occupied' ? 'bg-rose-400' : 'bg-amber-400' }}"></span>
                                            <span class="absolute top-0 right-0 w-3.5 h-3.5 rounded-full {{ $table['status'] === 'occupied' ? 'bg-rose-400' : 'bg-amber-400' }}"></span>
                                            <span class="absolute bottom-0 left-0 w-3.5 h-3.5 rounded-full {{ $table['status'] === 'occupied' ? 'bg-rose-400' : 'bg-amber-400' }}"></span>
                                            <span class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full {{ $table['status'] === 'occupied' ? 'bg-rose-400' : 'bg-amber-400' }}"></span>
                                        @endif
                                    </div>

                                    <span class="text-sm {{ $table['status'] === 'occupied' ? 'font-bold text-rose-300' : ($table['status'] === 'reserved' ? 'font-bold text-amber-300' : 'font-semibold text-slate-400') }}">{{ $table['capacity'] }} pax</span>

                                    @if($table['status'] === 'occupied' && $table['orders_count'] > 0)
                                        <span class="text-xs text-rose-300/90 flex items-center gap-1 mt-0.5">
                                            <x-heroicon-o-document-text class="w-3 h-3" />
                                            #{{ $table['first_order_id'] }} · {{ $table['elapsed_time'] }}
                                        </span>
                                    @elseif($table['status'] === 'reserved' && $table['has_reservation'])
                                        <span class="text-xs text-amber-300/90 truncate w-full text-center mt-0.5">{{ $table['reservation_info'] }}</span>
                                    @endif

                                    {{-- Botón de estado --}}
                                    <span class="w-full text-center py-1.5 rounded {{ $table['status'] === 'occupied' ? 'bg-rose-500/90 text-white' : ($table['status'] === 'reserved' ? 'bg-amber-500/30 text-amber-300 border border-amber-500/40' : 'bg-amber-500 hover:bg-amber-400 text-slate-950') }} text-xs font-black uppercase tracking-wider flex items-center justify-center gap-1 mt-1 shadow-sm transition-colors">
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
                    <div class="bg-[#0d0d0d] rounded-2xl border border-slate-800/80 p-12 text-center">
                        <h3 class="text-xl font-bold text-white mb-2">No hay mesas registradas</h3>
                        <p class="text-sm text-slate-400">Creá una mesa para empezar a gestionar tu salón.</p>
                    </div>
                @endforelse
            </section>

            {{-- COLUMNA DERECHA: PANEL DE DETALLE (30%, sticky) --}}
            <aside class="lg:col-span-5 bg-[#0d0d0d] border border-slate-800 rounded-2xl p-6 shadow-2xl sticky top-20" data-purpose="table-detail-panel">
                @if($selected)
                    {{-- Cabecera del panel --}}
                    <div class="flex items-start justify-between border-b border-slate-800 pb-4 mb-4">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs uppercase font-bold tracking-wider text-emerald-400">{{ $selected['zone_label'] }}</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-600"></span>
                                <span class="text-xs text-cyan-400 font-mono">#M-{{ $selected['number'] }}</span>
                            </div>
                            <h3 class="text-2xl font-black text-white mt-1 flex items-center gap-2">
                                <span>Mesa</span>
                                <span class="text-amber-400 text-4xl font-black">{{ $selected['number'] }}</span>
                            </h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-sm font-bold {{ $selected['status'] === 'occupied' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40' : ($selected['status'] === 'reserved' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40') }} flex items-center gap-1.5 shrink-0">
                            <span class="w-2 h-2 rounded-full {{ $selected['status'] === 'occupied' ? 'bg-rose-500 animate-pulse' : ($selected['status'] === 'reserved' ? 'bg-amber-400' : 'bg-emerald-400') }}"></span>
                            {{ $selected['status_label'] }}
                        </span>
                    </div>

                    {{-- Ficha de datos operativos --}}
                    <div class="space-y-3 bg-[#0f0f0f] p-3.5 rounded-xl border border-slate-800/90 text-sm">
                        <div class="flex justify-between items-center text-slate-300">
                            <span class="text-slate-400">Camarero Asignado:</span>
                            <span class="font-semibold text-white">{{ $selected['waiter_name'] }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-300">
                            <span class="text-slate-400">Permanencia:</span>
                            <span class="font-semibold text-amber-400">{{ $selected['permanence'] ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-300">
                            <span class="text-slate-400">Nº Comanda:</span>
                            <span class="font-mono text-cyan-400 font-bold">{{ $selected['first_order_id'] ? '#'.$selected['first_order_id'] : 'Sin Comanda' }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-300">
                            <span class="text-slate-400">Capacidad Máx:</span>
                            <span class="font-semibold text-white">{{ $selected['capacity'] }} {{ $selected['capacity'] === 1 ? 'Comensal' : 'Comensales' }}</span>
                        </div>
                        @if($selected['has_reservation'])
                            <div class="flex justify-between items-center text-slate-300 border-t border-slate-800 pt-3">
                                <span class="text-slate-400">Próxima Reserva:</span>
                                <span class="font-semibold text-amber-300">{{ $selected['reservation_info'] }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Comanda en curso --}}
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-sm font-bold text-slate-300 mb-2">
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                Comanda en Curso
                            </span>
                            <span class="text-emerald-400 font-semibold">
                                {{ collect($selected['orders'])->sum(fn ($o) => count($o['products'])) }} artículos
                            </span>
                        </div>

                        @php($orderItems = collect($selected['orders'])->flatMap(fn ($o) => collect($o['products'])->map(fn ($p) => $p + ['order_id' => $o['id']])))
                        @if($orderItems->isNotEmpty())
                            <div class="space-y-2 max-h-44 overflow-y-auto pr-1" id="order-items-list">
                                @foreach($orderItems as $item)
                                    <div class="flex items-center justify-between bg-[#070707] p-2.5 rounded-lg border border-slate-800 text-sm">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-5 h-5 rounded bg-amber-500/20 text-amber-400 font-bold flex items-center justify-center text-[10px] shrink-0">{{ $item['quantity'] }}x</span>
                                            <span class="text-slate-200 truncate">{{ $item['name'] }}</span>
                                        </div>
                                        <span class="font-semibold text-slate-100 shrink-0 ml-2">${{ number_format($item['quantity'] * $item['price'], 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="py-6 text-center text-slate-500 text-sm bg-[#070707] rounded-lg border border-dashed border-slate-800">
                                Mesa lista y sin consumición activa
                            </div>
                        @endif

                        {{-- Total a pagar --}}
                        <div class="mt-3 pt-3 border-t border-slate-800 flex justify-between items-baseline">
                            <span class="text-xs uppercase tracking-wider font-bold text-slate-400">Total a Pagar</span>
                            <span class="text-4xl font-black text-emerald-400">${{ number_format($selected['total_amount'], 2) }}</span>
                        </div>
                    </div>

                    {{-- Botones de acción primaria --}}
                    <div class="mt-5 space-y-2">
                        <button
                            wire:click="{{ $selected['first_order_id'] ? 'editOrder('.$selected['first_order_id'].')' : 'createOrderForTable' }}"
                            class="w-full py-4 bg-amber-500 hover:bg-amber-400 active:scale-[0.99] text-slate-950 font-black rounded-xl text-sm transition-all flex items-center justify-center gap-2 shadow-lg shadow-amber-500/20"
                        >
                            <x-heroicon-o-pencil-square class="w-4 h-4 stroke-[2.5]" />
                            <span>{{ $selected['first_order_id'] ? 'Ver / Gestionar Comanda' : 'Crear Comanda' }}</span>
                        </button>
                        <button
                            wire:click="prepareCobroMesa({{ $selected['id'] }})"
                            x-on:click="$dispatch('open-modal', { id: 'cobrar-mesa' })"
                            @disabled($selected['orders_count'] === 0)
                            class="w-full py-2.5 {{ $selected['orders_count'] === 0 ? 'bg-slate-800 text-slate-500 cursor-not-allowed' : 'bg-emerald-500 hover:bg-emerald-400 text-slate-950' }} active:scale-[0.99] font-bold rounded-xl text-xs transition-all flex items-center justify-center gap-2 shadow-md"
                            @if($selected['orders_count'] === 0) title="No hay pedidos activos para cobrar" @endif
                        >
                            <x-heroicon-o-credit-card class="w-4 h-4" />
                            <span>Cobrar Cuenta / Imprimir Factura</span>
                        </button>
                    </div>

                    {{-- Acciones rápidas secundarias --}}
                    <div class="mt-3 pt-3 border-t border-slate-800 grid grid-cols-2 gap-2 text-sm font-semibold text-slate-300">
                        <button disabled title="Próximamente" class="p-3.5 rounded-lg bg-[#0f0f0f] border border-slate-800 text-slate-500 cursor-not-allowed text-center flex items-center justify-center gap-1.5">
                            <x-heroicon-o-arrows-right-left class="w-3.5 h-3.5 text-cyan-400" />
                            Cambiar Mesa
                        </button>
                        <button disabled title="Próximamente" class="p-3.5 rounded-lg bg-[#0f0f0f] border border-slate-800 text-slate-500 cursor-not-allowed text-center flex items-center justify-center gap-1.5">
                            <x-heroicon-o-plus class="w-3.5 h-3.5 text-emerald-400" />
                            Unir Mesas
                        </button>
                        @if($this->isSuperAdmin())
                            <button
                                wire:click="toggleTableStatus({{ $selected['id'] }})"
                                class="p-3.5 rounded-lg bg-[#0f0f0f] border border-slate-800 hover:bg-slate-800 hover:text-white text-center flex items-center justify-center gap-1.5 transition-colors"
                            >
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-amber-400" />
                                Cambiar Estado
                            </button>
                        @else
                            <button disabled title="Solo super_admin puede cambiar el estado" class="p-3.5 rounded-lg bg-[#0f0f0f] border border-slate-800 text-slate-500 cursor-not-allowed text-center flex items-center justify-center gap-1.5">
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-amber-400" />
                                Cambiar Estado
                            </button>
                        @endif
                        <button
                            wire:click="freeTable({{ $selected['id'] }})"
                            wire:confirm="¿Liberar la mesa {{ $selected['number'] }}?"
                            class="p-3.5 rounded-lg bg-[#0f0f0f] border border-slate-800 hover:bg-slate-800 hover:text-white text-center flex items-center justify-center gap-1.5 transition-colors text-rose-400"
                        >
                            <x-heroicon-o-trash class="w-3.5 h-3.5 text-rose-400" />
                            Liberar Mesa
                        </button>
                    </div>
                @else
                    {{-- Estado vacío amigable --}}
                    <div class="py-14 text-center">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 mb-4">
                            <x-heroicon-o-map class="w-8 h-8" />
                        </div>
                        <h3 class="text-lg font-bold text-white mb-1">Seleccioná una mesa para ver sus detalles</h3>
                        <p class="text-xs text-slate-400">Tocá cualquier mesa del plano para ver sus especificaciones, comanda en curso y acciones de gestión.</p>
                    </div>
                @endif
            </aside>
        </div>

        {{-- ============ MODAL: AÑADIR NUEVA MESA ============ --}}
        <div x-show="openNewTable" x-cloak class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div x-on:click.outside="openNewTable = false" class="bg-[#0d0d0d] border border-amber-500/40 rounded-2xl w-full max-w-lg p-6 shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        Añadir Nueva Mesa al Plano
                    </h3>
                    <button x-on:click="openNewTable = false" class="text-slate-400 hover:text-white p-1">✕</button>
                </div>

                <form wire:submit="createTable" class="mt-4 space-y-4">
                    <div>
                        <label for="new-number" class="block text-sm font-semibold text-slate-300 mb-1">Número o Código de Mesa</label>
                        <input
                            id="new-number"
                            type="number"
                            wire:model="newNumber"
                            min="1"
                            placeholder="Ej: 50"
                            class="w-full bg-[#111111] border rounded-lg p-3.5 text-white text-sm placeholder-slate-500 focus:border-amber-500 focus:outline-none {{ $errors->has('newNumber') ? 'border-rose-500' : 'border-slate-700' }}"
                        />
                        @error('newNumber')
                            <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="new-zone" class="block text-sm font-semibold text-slate-300 mb-1">Zona Asignada</label>
                        <select
                            id="new-zone"
                            wire:model="newLocation"
                            class="w-full bg-[#111111] border rounded-lg p-3.5 text-white text-sm focus:border-amber-500 focus:outline-none {{ $errors->has('newLocation') ? 'border-rose-500' : 'border-slate-700' }}"
                        >
                            @foreach(\App\Filament\Pages\TableMap::ZONE_LABELS as $zoneKey => $zoneLabel)
                                <option value="{{ $zoneKey }}">{{ $zoneLabel }}</option>
                            @endforeach
                        </select>
                        @error('newLocation')
                            <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="new-capacity" class="block text-sm font-semibold text-slate-300 mb-1">Capacidad Comensales (Pax)</label>
                        <input
                            id="new-capacity"
                            type="number"
                            wire:model="newCapacity"
                            min="1"
                            max="20"
                            class="w-full bg-[#111111] border rounded-lg p-3.5 text-white text-sm placeholder-slate-500 focus:border-amber-500 focus:outline-none {{ $errors->has('newCapacity') ? 'border-rose-500' : 'border-slate-700' }}"
                        />
                        @error('newCapacity')
                            <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="pt-3 flex gap-2">
                        <button type="button" x-on:click="openNewTable = false" class="flex-1 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-colors">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" class="flex-1 py-2.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-black transition-colors">Guardar Mesa</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ============ TOAST DE FEEDBACK (Alpine) ============ --}}
        <div
            x-data="{ show: false, msg: '' }"
            x-on:table-selected.window="msg = $event.detail.message; show = true; clearTimeout(window.__moodiToast); window.__moodiToast = setTimeout(() => show = false, 2500)"
            class="fixed bottom-5 right-5 z-[60] bg-[#111111] border border-amber-500 text-amber-400 px-4 py-2.5 rounded-xl shadow-2xl text-xs font-semibold flex items-center gap-2 transition-all duration-300"
            :class="show ? 'translate-y-0 opacity-100' : 'translate-y-20 opacity-0'"
        >
            <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-400" />
            <span x-text="msg">Mesa seleccionada</span>
        </div>

        {{-- ============ MODAL DE COBRO (reusado, sin rediseñar) ============ --}}
        <x-filament::modal id="cobrar-mesa" width="2xl">
            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    <div class="bg-green-500 rounded-xl p-3">
                        <x-heroicon-o-currency-dollar class="w-8 h-8 text-white" />
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white">Cobrar Mesa</h3>
                </div>
            </x-slot>

            <div class="space-y-6">
                {{-- Total sin descuento --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 border-4 border-yellow-400">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-gray-700 dark:text-gray-300">Subtotal</span>
                        <span class="text-3xl font-black text-gray-900 dark:text-white">
                            ${{ number_format($totalAmount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Método de Pago --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">
                        💳 Método de Pago
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <button
                            wire:click="$set('paymentMethod', 'cash')"
                            class="flex flex-col items-center justify-center p-4 rounded-lg border-4 font-bold transition-all
                                {{ $paymentMethod === 'cash' ? 'bg-green-500 border-black text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-green-500' }}">
                            <x-heroicon-o-banknotes class="w-8 h-8 mb-2" />
                            Efectivo
                        </button>
                        <button
                            wire:click="$set('paymentMethod', 'card')"
                            class="flex flex-col items-center justify-center p-4 rounded-lg border-4 font-bold transition-all
                                {{ $paymentMethod === 'card' ? 'bg-green-500 border-black text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-green-500' }}">
                            <x-heroicon-o-credit-card class="w-8 h-8 mb-2" />
                            Tarjeta
                        </button>
                        <button
                            wire:click="$set('paymentMethod', 'transfer')"
                            class="flex flex-col items-center justify-center p-4 rounded-lg border-4 font-bold transition-all
                                {{ $paymentMethod === 'transfer' ? 'bg-green-500 border-black text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-green-500' }}">
                            <x-heroicon-o-arrows-right-left class="w-8 h-8 mb-2" />
                            Transferencia
                        </button>
                    </div>
                </div>

                {{-- Descuentos --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">
                        🏷️ Aplicar Descuentos (Opcional)
                    </label>
                    @if($discounts->count() > 0)
                        <div class="space-y-2 max-h-48 overflow-y-auto bg-white dark:bg-gray-800 rounded-lg border-2 border-gray-300 p-3">
                            @foreach($discounts as $discount)
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-2 border-transparent hover:border-yellow-400 transition-all">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedDiscounts"
                                        value="{{ $discount->id }}"
                                        class="w-5 h-5 text-yellow-400 border-gray-300 rounded focus:ring-yellow-400">
                                    <div class="flex-1">
                                        <div class="font-bold text-gray-900 dark:text-white">{{ $discount->name }}</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ $discount->type === 'percentage' ? $discount->value . '%' : '$' . number_format($discount->value, 0, ',', '.') }} de descuento
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-500 italic">
                            No hay descuentos disponibles
                        </div>
                    @endif
                </div>

                {{-- Descuento aplicado --}}
                @if($discountAmount > 0)
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 border-2 border-yellow-400">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-yellow-800 dark:text-yellow-300">Descuento Total</span>
                            <span class="text-2xl font-black text-yellow-600 dark:text-yellow-400">
                                -${{ number_format($discountAmount, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @endif

                {{-- Total final --}}
                <div class="bg-black rounded-lg p-6 border-4 border-yellow-400">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-lg font-bold text-yellow-400 mb-1">TOTAL A COBRAR</p>
                            <p class="text-sm text-yellow-400/75">{{ ucfirst($paymentMethod === 'cash' ? 'Efectivo' : ($paymentMethod === 'card' ? 'Tarjeta' : 'Transferencia')) }}</p>
                        </div>
                        <span class="text-5xl font-black text-yellow-400">
                            ${{ number_format(max(0, $totalAmount - $discountAmount), 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            <x-slot name="footerActions">
                <div class="flex gap-3 w-full">
                    <button
                        x-on:click="$dispatch('close-modal', { id: 'cobrar-mesa' })"
                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-black py-3 px-6 rounded-lg text-lg">
                        Cancelar
                    </button>
                    <button
                        wire:click="cobrarMesa"
                        wire:loading.attr="disabled"
                        class="flex-1 bg-green-500 hover:bg-green-600 text-white font-black py-3 px-6 rounded-lg border-4 border-yellow-400 text-lg flex items-center justify-center gap-2">
                        <x-heroicon-o-check-circle class="w-6 h-6" />
                        <span wire:loading.remove>Confirmar Cobro</span>
                        <span wire:loading>Procesando...</span>
                    </button>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>