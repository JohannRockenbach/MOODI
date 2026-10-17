<x-filament-panels::page x-data="{ openNewTable: false }">
    {{-- Gestión de Mesas — lenguaje Stitch "Negro Neutro y Ámbar": fondo neutro #0d0d0d,
         tarjetas sobrias y ámbar #fbbc48 como ÚNICO acento caliente (sin rosa/cian salvo
         estados críticos: ocupada → rojo). --}}
    <div class="space-y-6" style="font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;">
        @php
            $kpis = [
                [
                    'label' => 'DISPONIBLES',
                    'value' => $stats['available'],
                    'sub' => 'Listas para asignar',
                    'value_color' => 'text-amber-600 dark:text-[#fbbc48]',
                    'icon' => 'check',
                    'icon_color' => 'text-amber-600 dark:text-[#fbbc48]',
                    'icon_bg' => 'bg-amber-100 border-amber-200 dark:bg-[#fbbc48]/10 dark:border-[#fbbc48]/25',
                ],
                [
                    'label' => 'OCUPADAS',
                    'value' => $stats['occupied'],
                    'sub' => 'Con comensales en sala',
                    'value_color' => 'text-rose-600 dark:text-rose-400',
                    'icon' => 'fire',
                    'icon_color' => 'text-rose-600 dark:text-rose-400',
                    'icon_bg' => 'bg-rose-100 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/25',
                ],
                [
                    'label' => 'RESERVADAS',
                    'value' => $stats['reserved'],
                    'sub' => 'Bloqueadas por reserva',
                    'value_color' => 'text-amber-600 dark:text-[#fbbc48]',
                    'icon' => 'clock',
                    'icon_color' => 'text-amber-600 dark:text-[#fbbc48]',
                    'icon_bg' => 'bg-amber-100 border-amber-200 dark:bg-[#fbbc48]/15 dark:border-[#fbbc48]/35',
                ],
                [
                    'label' => 'MANTENIMIENTO',
                    'value' => $stats['maintenance'],
                    'sub' => 'Fuera de servicio',
                    'value_color' => 'text-slate-600 dark:text-slate-400',
                    'icon' => 'wrench',
                    'icon_color' => 'text-slate-600 dark:text-slate-400',
                    'icon_bg' => 'bg-slate-100 border-slate-200 dark:bg-slate-800 dark:border-slate-700',
                ],
            ];
        @endphp

        {{-- ============ HEADER: título, buscador, filtros y acción ============ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-[#0d0d0d]">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Mesas</h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 px-2.5 py-1 text-sm font-medium text-amber-700 dark:border-[#fbbc48]/40 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                            En Directo
                        </span>
                    </div>
                    <p class="hidden text-base text-slate-500 dark:text-slate-400 md:block">Estado del salón en tiempo real</p>
                </div>

                <div class="flex w-full flex-wrap items-center gap-2.5 lg:w-auto">
                    {{-- Buscador: número / mozo / ubicación --}}
                    <div class="relative flex-1 sm:w-80">
                        <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="text"
                            placeholder="Buscar por número, mozo o ubicación..."
                            class="w-full rounded-lg border border-slate-300 bg-slate-100 py-3 pl-9 pr-3 text-base text-slate-700 placeholder-slate-400 transition-colors focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-slate-800 dark:bg-[#101010] dark:text-slate-200 dark:placeholder-slate-500"
                        />
                    </div>

                    {{-- Filtro segmented por zona --}}
                    <div class="flex rounded-lg border border-slate-300 bg-slate-100 p-1 text-sm dark:border-slate-800 dark:bg-[#101010]">
                        @foreach(['all' => 'Todas', 'terraza' => 'Terraza', 'salon' => 'Salón', 'barra' => 'Barra'] as $zoneKey => $zoneLabel)
                            <button
                                wire:click="$set('activeZone', '{{ $zoneKey }}')"
                                class="rounded-md px-3 py-1.5 transition-colors {{ $activeZone === $zoneKey ? 'bg-amber-500 font-bold text-white dark:bg-[#fbbc48] dark:text-[#422c00]' : 'font-medium text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200' }}"
                            >
                                {{ $zoneLabel }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Filtro segmented por estado --}}
                    <div class="flex rounded-lg border border-slate-300 bg-slate-100 p-1 text-sm dark:border-slate-800 dark:bg-[#101010]">
                        @foreach(['all' => 'Todos', 'available' => 'Disponible', 'occupied' => 'Ocupada', 'reserved' => 'Reservada', 'maintenance' => 'Mantenimiento'] as $statusKey => $statusLabel)
                            <button
                                wire:click="$set('activeStatus', '{{ $statusKey }}')"
                                class="rounded-md px-3 py-1.5.5 transition-colors {{ $activeStatus === $statusKey ? 'bg-amber-500 font-bold text-white dark:bg-[#fbbc48] dark:text-[#422c00]' : 'font-medium text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200' }}"
                            >
                                {{ $statusLabel }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Nueva Mesa --}}
                    <button
                        x-on:click="openNewTable = true"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-5 py-3 text-sm font-bold text-white shadow-md transition-all hover:bg-amber-400 dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]"
                    >
                        <x-heroicon-o-plus class="h-3.5 w-3.5 stroke-[2.5]" />
                        <span>Nueva Mesa</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ============ KPIs (4) ============ --}}
        <section class="grid grid-cols-2 gap-4 sm:gap-4 md:grid-cols-4" data-purpose="kpi-metrics-grid">
            @foreach($kpis as $kpi)
                <div class="relative flex items-center justify-between overflow-hidden rounded-xl border border-slate-200 bg-white p-6 shadow-md dark:border-slate-800 dark:bg-[#0d0d0d]">
                    <div class="z-10">
                        <p class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 sm:text-base">{{ $kpi['label'] }}</p>
                        <p class="mt-1 text-4xl font-black sm:text-5xl {{ $kpi['value_color'] }}">{{ $kpi['value'] }}</p>
                        <p class="mt-0.5 text-base text-slate-500">{{ $kpi['sub'] }}</p>
                    </div>
                    <div class="flex h-16 w-16 items-center justify-center rounded-xl border {{ $kpi['icon_bg'] }} {{ $kpi['icon_color'] }}">
                        @if($kpi['icon'] === 'check')
                            <x-heroicon-o-check-circle class="h-8 w-8" />
                        @elseif($kpi['icon'] === 'fire')
                            <x-heroicon-s-fire class="h-8 w-8" />
                        @elseif($kpi['icon'] === 'clock')
                            <x-heroicon-o-clock class="h-8 w-8" />
                        @else
                            <x-heroicon-o-wrench-screwdriver class="h-8 w-8" />
                        @endif
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ============ GRID DE TARJETAS DE MESA (agrupado por zona) ============ --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-[#0d0d0d]" data-purpose="mesas-grid">
            @php
                $visible = $this->visibleTables;
                $porZona = collect($visible)
                    ->groupBy('zone')
                    ->sortBy(fn ($mesas, $zona) => array_search($zona, array_keys(\App\Filament\Pages\GestionMesas::ZONE_LABELS), true));
            @endphp
            @if(count($visible) > 0)
                @foreach($porZona as $zona => $mesas)
                    {{-- Bloque de zona: banda de título ámbar notoria + cuerpo con mesas --}}
                    <article class="mb-6 overflow-hidden rounded-2xl border-2 border-amber-500/40 shadow-md dark:border-[#fbbc48]/25 dark:bg-[#080808]" wire:key="zona-{{ $zona }}">
                        <header class="flex flex-wrap items-center justify-between gap-3 border-b-2 border-amber-500/40 bg-amber-100 px-5 py-3.5 dark:border-[#fbbc48]/20 dark:bg-[#fbbc48]/15">
                            <h2 class="flex items-center gap-3 text-2xl font-black uppercase tracking-wider text-amber-900 dark:text-[#fbbc48]">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-md shadow-amber-500/30 dark:bg-[#fbbc48] dark:text-[#422c00]">
                                    @if($zona === 'terraza')
                                        <x-heroicon-o-sun class="h-5 w-5" />
                                    @elseif($zona === 'barra')
                                        <x-heroicon-o-beaker class="h-5 w-5" />
                                    @else
                                        <x-heroicon-o-building-storefront class="h-5 w-5" />
                                    @endif
                                </span>
                                {{ \App\Filament\Pages\GestionMesas::ZONE_LABELS[$zona] ?? $zona }}
                            </h2>
                            <span class="rounded-xl bg-amber-500 px-4 py-2 text-base font-black text-white tracking-wide shadow-md shadow-amber-500/20 dark:bg-[#fbbc48] dark:text-[#422c00]">
                                {{ count($mesas) }} {{ count($mesas) === 1 ? 'mesa' : 'mesas' }}
                            </span>
                        </header>

                        <div class="p-4 sm:p-5">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        @foreach($mesas as $mesa)
                        @php
                            $statusStyles = match ($mesa['status']) {
                                'occupied' => [
                                    'card' => 'border-rose-500/50 bg-rose-50/40 dark:border-rose-500/30 dark:bg-rose-500/[0.06]',
                                    'badge' => 'bg-rose-100 text-rose-700 border-rose-300 dark:bg-rose-500/15 dark:text-rose-300 dark:border-rose-500/30',
                                    'dot' => 'bg-rose-500 animate-pulse',
                                    'number' => 'text-rose-700 dark:text-rose-300',
                                ],
                                'reserved' => [
                                    'card' => 'border-amber-500/40 bg-amber-50/40 dark:border-[#fbbc48]/30 dark:bg-[#fbbc48]/[0.05]',
                                    'badge' => 'bg-amber-100 text-amber-700 border-amber-300 dark:bg-[#fbbc48]/15 dark:text-[#fbbc48] dark:border-[#fbbc48]/30',
                                    'dot' => 'bg-amber-500',
                                    'number' => 'text-amber-700 dark:text-[#fbbc48]',
                                ],
                                'maintenance' => [
                                    'card' => 'border-dashed border-slate-300 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/40',
                                    'badge' => 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
                                    'dot' => 'bg-slate-400',
                                    'number' => 'text-slate-600 dark:text-slate-400',
                                ],
                                default => [
                                    'card' => 'border-slate-300 bg-white dark:border-slate-800 dark:bg-[#101010]',
                                    'badge' => 'bg-amber-500 text-white border-amber-500/60 dark:bg-[#fbbc48]/15 dark:text-[#fbbc48] dark:border-[#fbbc48]/30',
                                    'dot' => 'bg-amber-500',
                                    'number' => 'text-slate-900 dark:text-white',
                                ],
                            };
                        @endphp
                        <article wire:key="mesa-{{ $mesa['id'] }}" class="flex flex-col gap-3 rounded-2xl border p-5 shadow-md transition-all hover:-translate-y-0.5 hover:shadow-lg {{ $statusStyles['card'] }}">
                            {{-- Número + estado --}}
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="text-4xl font-black leading-none {{ $statusStyles['number'] }}">{{ $mesa['number'] }}</span>
                                    <div class="mt-1 flex items-center gap-1.5 text-base font-semibold text-slate-500 dark:text-slate-400">
                                        <x-heroicon-o-map-pin class="h-3.5 w-3.5" />
                                        {{ $mesa['zone_label'] }}
                                    </div>
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-bold {{ $statusStyles['badge'] }}">
                                    <span class="h-2 w-2 rounded-full {{ $statusStyles['dot'] }}"></span>
                                    {{ $mesa['status_label'] }}
                                </span>
                            </div>

                            {{-- Datos operativos --}}
                            <div class="flex flex-col gap-1.5 border-t border-slate-200/60 pt-3 text-base dark:border-slate-800/60">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Capacidad</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $mesa['capacity'] }} {{ $mesa['capacity'] === 1 ? 'pax' : 'pax' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Mozo</span>
                                    <span class="max-w-[55%] truncate font-bold text-slate-800 dark:text-slate-200">{{ $mesa['waiter_name'] }}</span>
                                </div>
                                @if($mesa['status'] === 'occupied')
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500 dark:text-slate-400">Pedidos activos</span>
                                        <span class="font-bold text-rose-600 dark:text-rose-400">{{ $mesa['active_orders'] }}</span>
                                    </div>
                                @elseif($mesa['status'] === 'reserved' && $mesa['next_reservation'])
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500 dark:text-slate-400">Próxima reserva</span>
                                        <span class="font-bold text-amber-600 dark:text-[#fbbc48]">{{ $mesa['next_reservation'] }}h</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Acciones --}}
                            <div class="grid grid-cols-2 gap-2 border-t border-slate-200/60 pt-3 dark:border-slate-800/60">
                                <button
                                    wire:click="editTable({{ $mesa['id'] }})"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-amber-500 px-4 py-3 text-sm font-black text-white shadow-sm transition-all hover:bg-amber-400 active:scale-[0.98] dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]"
                                >
                                    <x-heroicon-o-pencil-square class="h-3.5 w-3.5" />
                                    Editar
                                </button>
                                @if($mesa['status'] === 'occupied')
                                    <button
                                        wire:click="freeTable({{ $mesa['id'] }})"
                                        wire:confirm="¿Liberar la mesa {{ $mesa['number'] }}?"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#181c22] dark:text-slate-300 dark:hover:bg-[#272a31]"
                                    >
                                        <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />
                                        Liberar
                                    </button>
                                @else
                                    <span class="rounded-lg bg-slate-50 px-4 py-3 text-center text-sm font-semibold text-slate-400 dark:bg-slate-900/40 dark:text-slate-500">
                                        {{ in_array($mesa['status'], ['reserved', 'maintenance'], true) ? 'Sin acción' : 'Liberar' }}
                                    </span>
                                @endif
                                @if($this->isSuperAdmin())
                                    <button
                                        wire:click="toggleTableStatus({{ $mesa['id'] }})"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-amber-500/50 px-4 py-3 text-sm font-bold text-amber-700 transition-colors hover:bg-amber-50 dark:border-[#fbbc48]/40 dark:text-[#fbbc48] dark:hover:bg-[#fbbc48]/10"
                                        title="{{ $mesa['status'] === 'maintenance' ? 'Volver a disponible' : 'Poner en mantenimiento' }}"
                                    >
                                        <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />
                                        {{ $mesa['status'] === 'maintenance' ? 'Activar' : 'Mantenimiento' }}
                                    </button>
                                    <button
                                        wire:click="deleteTable({{ $mesa['id'] }})"
                                        wire:confirm="¿Eliminar la mesa {{ $mesa['number'] }}? Esta acción no se puede deshacer."
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700 transition-colors hover:bg-rose-100 dark:bg-[#93000a]/15 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/25"
                                    >
                                        <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                        Eliminar
                                    </button>
                                @else
                                    <span class="rounded-lg bg-slate-50 px-4 py-3 text-center text-sm font-semibold text-slate-400 dark:bg-slate-900/40 dark:text-slate-500" title="Solo super_admin">
                                        Restringido
                                    </span>
                                    <span class="rounded-lg bg-slate-50 px-4 py-3 text-center text-sm font-semibold text-slate-400 dark:bg-slate-900/40 dark:text-slate-500" title="Solo super_admin">
                                        Restringido
                                    </span>
                                @endif
                            </div>
                        </article>
                            @endforeach
                        </div>
                        </div>
                    </article>
                @endforeach
            @else
                {{-- Estado vacío según filtros --}}
                <div class="py-14 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-amber-300 bg-amber-100 text-amber-600 dark:border-[#fbbc48]/20 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">
                        <x-heroicon-o-table-cells class="h-8 w-8" />
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ count($this->tables) > 0 ? 'No hay mesas con esos filtros' : 'No hay mesas registradas' }}</h3>
                    <p class="mt-1 text-base text-slate-500 dark:text-slate-400">{{ count($this->tables) > 0 ? 'Ajustá la búsqueda o los filtros para ver más mesas.' : 'Creá una mesa para empezar a gestionar tu salón.' }}</p>
                </div>
            @endif
        </section>

        {{-- ============ MODAL: NUEVA MESA (inline, estilo TableMap) ============ --}}
        <div x-show="openNewTable" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm" x-transition.opacity>
            <div x-on:click.outside="openNewTable = false" class="w-full max-w-lg rounded-2xl border border-amber-500/60 bg-white p-6 shadow-2xl dark:border-[#fbbc48]/40 dark:bg-[#0d0d0d]">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <h3 class="flex items-center gap-2 text-xl font-bold text-slate-900 dark:text-white">
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                        Añadir Nueva Mesa
                    </h3>
                    <button x-on:click="openNewTable = false" class="p-1 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form wire:submit="createTable" class="mt-4 space-y-4">
                    <div>
                        <label for="new-number" class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Número o Código de Mesa</label>
                        <input
                            id="new-number"
                            type="number"
                            wire:model="newNumber"
                            min="1"
                            placeholder="Ej: 50"
                            class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 placeholder-slate-400 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white dark:placeholder-slate-500 {{ $errors->has('newNumber') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                        />
                        @error('newNumber')
                            <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="new-zone" class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Zona Asignada</label>
                        <select
                            id="new-zone"
                            wire:model="newLocation"
                            class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white {{ $errors->has('newLocation') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                        >
                            @foreach(\App\Filament\Pages\GestionMesas::ZONE_LABELS as $zoneKey => $zoneLabel)
                                <option value="{{ $zoneKey }}">{{ $zoneLabel }}</option>
                            @endforeach
                        </select>
                        @error('newLocation')
                            <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="new-capacity" class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Capacidad Comensales (Pax)</label>
                        <input
                            id="new-capacity"
                            type="number"
                            wire:model="newCapacity"
                            min="1"
                            max="20"
                            class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 placeholder-slate-400 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white dark:placeholder-slate-500 {{ $errors->has('newCapacity') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                        />
                        @error('newCapacity')
                            <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex gap-2 pt-3">
                        <button type="button" x-on:click="openNewTable = false" class="flex-1 rounded-lg bg-slate-100 py-2.5 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" class="flex-1 rounded-lg bg-amber-500 py-2.5 text-sm font-black text-white transition-colors hover:bg-amber-400 dark:bg-[#fbbc48] dark:text-[#422c00]">Guardar Mesa</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ============ TOAST DE FEEDBACK (Alpine, patrón TableMap) ============ --}}
        <div
            x-data="{ show: false, msg: '' }"
            x-on:table-selected.window="msg = $event.detail.message; show = true; clearTimeout(window.__moodiToast); window.__moodiToast = setTimeout(() => show = false, 2500)"
            class="fixed bottom-5 right-5 z-[60] flex items-center gap-2 rounded-xl border border-amber-500 bg-white px-5 py-3 text-sm font-semibold text-amber-600 shadow-2xl transition-all duration-300 dark:bg-[#111111] dark:text-[#fbbc48]"
            :class="show ? 'translate-y-0 opacity-100' : 'translate-y-20 opacity-0'"
        >
            <x-heroicon-o-check-circle class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
            <span x-text="msg">Mesa creada</span>
        </div>
    </div>
</x-filament-panels::page>