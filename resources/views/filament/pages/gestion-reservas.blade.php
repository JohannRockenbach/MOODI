<x-filament-panels::page>
    <div class="space-y-6" data-root>
    @php
        $kpis = [
            [
                'label' => 'PENDIENTES',
                'value' => $stats['pending'],
                'sub' => 'Esperando confirmación',
                'value_color' => 'text-amber-600 dark:text-[#fbbc48]',
                'card' => 'border-amber-200 dark:border-amber-500/30',
                'icon' => 'clock',
                'icon_color' => 'text-amber-600 dark:text-[#fbbc48]',
                'icon_bg' => 'bg-amber-100 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20',
            ],
            [
                'label' => 'CONFIRMADAS',
                'value' => $stats['confirmed'],
                'sub' => 'Mesa asegurada',
                'value_color' => 'text-green-600 dark:text-green-400',
                'card' => 'border-green-200 dark:border-green-500/30',
                'icon' => 'check',
                'icon_color' => 'text-green-600 dark:text-green-400',
                'icon_bg' => 'bg-green-100 border-green-200 dark:bg-green-500/10 dark:border-green-500/20',
            ],
            [
                'label' => 'CANCELADAS',
                'value' => $stats['cancelled'],
                'sub' => 'Reservas anuladas',
                'value_color' => 'text-rose-600 dark:text-rose-400',
                'card' => 'border-rose-200 dark:border-rose-500/30',
                'icon' => 'x',
                'icon_color' => 'text-rose-600 dark:text-rose-400',
                'icon_bg' => 'bg-rose-100 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20',
            ],
            [
                'label' => 'HOY',
                'value' => $stats['today'],
                'sub' => 'Reservas para hoy',
                'value_color' => 'text-emerald-600 dark:text-emerald-400',
                'card' => 'border-emerald-200 dark:border-emerald-500/30',
                'icon' => 'calendar',
                'icon_color' => 'text-emerald-700 dark:text-emerald-400',
                'icon_bg' => 'bg-emerald-100 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/25',
            ],
        ];
    @endphp

        {{-- ============ HEADER: identidad calendario + fecha destacada ============ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-[#272a31] dark:bg-[#181c22]">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    {{-- Fecha destacada (el "hoy" del calendario) --}}
                    <div class="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-2xl bg-amber-500 shadow-md shadow-amber-500/30 dark:bg-[#fbbc48] dark:text-[#422c00]">
                        <span class="text-sm font-black uppercase leading-none tracking-wider text-white/90 dark:text-[#422c00]/80">{{ now()->locale('es')->translatedFormat('M') }}</span>
                        <span class="text-2xl font-black leading-none text-white dark:text-[#422c00]">{{ now()->format('d') }}</span>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-xl font-bold uppercase tracking-wider text-slate-900 dark:text-[#e0e2ec]">Reservas</span>
                        <span class="block text-base font-semibold text-amber-600 dark:text-[#fbbc48]">
                            {{ now()->locale('es')->translatedFormat('l d/m/Y') }}
                        </span>
                        @if($stats['today'] > 0)
                            <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-sm font-bold text-emerald-700 dark:text-emerald-400">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                {{ $stats['today'] }} {{ $stats['today'] === 1 ? 'reserva' : 'reservas' }} para hoy
                            </span>
                        @endif
                    </div>
                </div>

                <button
                    wire:click="createReservation"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-5 py-3 text-sm font-bold text-white shadow-md transition-all hover:bg-amber-400 dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]"
                >
                    <x-heroicon-o-plus class="h-3.5 w-3.5 stroke-[2.5]" />
                    Nueva Reserva
                </button>
            </div>

            {{-- Barra de filtros + búsqueda --}}
            <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-slate-200 pt-4 dark:border-[#272a31]">
                <div class="relative flex-1 sm:w-80">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Buscar por cliente o mesa..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-100 py-3 pl-9 pr-3 text-base text-slate-700 placeholder-slate-400 transition-colors focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-[#414753] dark:bg-[#0f0f0f] dark:text-slate-200 dark:placeholder-slate-500"
                    />
                </div>

                {{-- Filtro segmented por fecha --}}
                <div class="flex rounded-lg border border-slate-300 bg-slate-100 p-1 text-sm dark:border-[#414753] dark:bg-[#0f0f0f]">
                    @foreach(['all' => 'Todas', 'today' => 'Hoy', 'upcoming' => 'Próximas'] as $dateKey => $dateLabel)
                        <button
                            wire:click="$set('activeDate', '{{ $dateKey }}')"
                            class="rounded-md px-3 py-1.5 transition-colors {{ $activeDate === $dateKey ? 'bg-amber-500 font-bold text-white dark:bg-[#fbbc48] dark:text-[#422c00]' : 'font-medium text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200' }}"
                        >
                            {{ $dateLabel }}
                        </button>
                    @endforeach
                </div>

                {{-- Filtro segmented por estado --}}
                <div class="flex rounded-lg border border-slate-300 bg-slate-100 p-1 text-sm dark:border-[#414753] dark:bg-[#0f0f0f]">
                    @foreach(['all' => 'Todos', 'pending' => 'Pendientes', 'confirmed' => 'Confirmadas', 'cancelled' => 'Canceladas'] as $statusKey => $statusLabel)
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

        {{-- ============ KPIs (4) ============ --}}
        <section class="grid grid-cols-2 gap-4 sm:gap-4 md:grid-cols-4" data-purpose="kpi-metrics-grid">
            @foreach($kpis as $kpi)
                <div class="relative flex items-center justify-between overflow-hidden rounded-xl border border-slate-200 bg-white p-6 shadow-md dark:border-[#272a31] dark:bg-[#181c22]">
                    <div class="z-10">
                        <p class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 sm:text-base">{{ $kpi['label'] }}</p>
                        <p class="mt-1 text-4xl font-black sm:text-5xl {{ $kpi['value_color'] }}">{{ $kpi['value'] }}</p>
                        <p class="mt-0.5 text-base text-slate-500">{{ $kpi['sub'] }}</p>
                    </div>
                    <div class="flex h-16 w-16 items-center justify-center rounded-xl border {{ $kpi['icon_bg'] }} {{ $kpi['icon_color'] }}">
                        @if($kpi['icon'] === 'clock')
                            <x-heroicon-o-clock class="h-8 w-8" />
                        @elseif($kpi['icon'] === 'check')
                            <x-heroicon-o-check-circle class="h-8 w-8" />
                        @elseif($kpi['icon'] === 'calendar')
                            <x-heroicon-o-calendar class="h-8 w-8" />
                        @else
                            <x-heroicon-o-x-circle class="h-8 w-8" />
                        @endif
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ============ GRID DE TARJETAS DE RESERVA ============ --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-[#272a31] dark:bg-[#181c22]" data-purpose="reservas-grid">
            @php
                $visible = $this->visibleReservations;
            @endphp
            @if(count($visible) > 0)
                <div class="flex flex-col gap-3">
                    @foreach($visible as $reserva)
                        @php
                            $statusBadge = match ($reserva['status']) {
                                'confirmed' => ['class' => 'bg-green-500/15 text-green-700 border-green-500/40 dark:text-green-400 dark:border-green-500/40', 'dot' => 'bg-green-500'],
                                'cancelled' => ['class' => 'bg-rose-500/15 text-rose-700 border-rose-500/40 dark:text-rose-400 dark:border-rose-500/40', 'dot' => 'bg-rose-500'],
                                default => ['class' => 'bg-amber-500/15 text-amber-700 border-amber-500/40 dark:text-[#fbbc48] dark:border-[#fbbc48]/40', 'dot' => 'bg-amber-500 animate-pulse'],
                            };
                        @endphp
                        <article wire:key="reserva-{{ $reserva['id'] }}" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-[#414753] dark:bg-[#1c2026] md:flex-row md:items-center md:gap-4">
                            {{-- Estado: punto de color (columna izquierda) --}}
                            <div class="flex items-center gap-3 md:w-40 md:shrink-0 md:flex-col md:items-start md:gap-1">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-[#272a31] dark:text-[#c1c6d5]">
                                    <x-heroicon-o-user class="h-5 w-5" />
                                </span>
                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-bold md:w-full md:justify-center {{ $statusBadge['class'] }}">
                                    <span class="h-2 w-2 rounded-full {{ $statusBadge['dot'] }}"></span>
                                    {{ $reserva['status_label'] }}
                                </span>
                            </div>

                            {{-- Datos: cliente, mesa, fecha (columna central) --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="truncate text-xl font-black text-slate-900 dark:text-[#e0e2ec]">{{ $reserva['customer_name'] }}</span>
                                    <span class="flex items-center gap-1 text-base font-bold text-amber-600 dark:text-[#fbbc48]">
                                        <x-heroicon-o-table-cells class="h-3.5 w-3.5" />
                                        Mesa #{{ $reserva['table_number'] ?? 'N/A' }}
                                    </span>
                                    <span class="text-base font-semibold text-slate-500 dark:text-[#c1c6d5]">{{ $reserva['guest_count'] }} {{ $reserva['guest_count'] === 1 ? 'persona' : 'personas' }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-base text-slate-500 dark:text-[#c1c6d5]">
                                    <span class="flex items-center gap-1.5 font-mono text-base font-bold text-slate-900 dark:text-[#e0e2ec]">
                                        <x-heroicon-o-calendar class="h-4 w-4 text-amber-600 dark:text-[#fbbc48]" />
                                        {{ $reserva['reservation_time']->format('d/m/Y H:i') }}
                                    </span>
                                    <span class="font-semibold">{{ $reserva['reservation_time']->locale('es')->diffForHumans(null, true) }}</span>
                                </div>
                            </div>

                            {{-- Acciones (columna derecha) --}}
                            <div class="flex shrink-0 items-center gap-2">
                                @if($reserva['status'] === 'pending')
                                    <button
                                        wire:click="confirmReservation({{ $reserva['id'] }})"
                                        wire:confirm="¿Confirmar la reserva de {{ $reserva['customer_name'] }} (Mesa #{{ $reserva['table_number'] }})?"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-green-500 px-4 py-3 text-sm font-black text-white shadow-sm transition-all hover:bg-green-600 active:scale-[0.98] dark:bg-green-500 dark:text-[#052f1a] dark:hover:bg-green-400"
                                    >
                                        <x-heroicon-o-check-circle class="h-4 w-4" />
                                        Confirmar
                                    </button>
                                    <button
                                        wire:click="cancelReservation({{ $reserva['id'] }})"
                                        wire:confirm="¿Cancelar la reserva de {{ $reserva['customer_name'] }}?"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-red-50 px-4 py-3 text-sm font-bold text-red-700 transition-all hover:bg-red-100 dark:bg-[#93000a]/20 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/30"
                                    >
                                        <x-heroicon-o-x-circle class="h-4 w-4" />
                                        Cancelar
                                    </button>
                                @elseif($reserva['status'] === 'confirmed')
                                    <button
                                        wire:click="cancelReservation({{ $reserva['id'] }})"
                                        wire:confirm="¿Cancelar la reserva confirmada de {{ $reserva['customer_name'] }}? La mesa se liberará."
                                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-red-50 px-4 py-3 text-sm font-bold text-red-700 transition-all hover:bg-red-100 dark:bg-[#93000a]/20 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/30"
                                    >
                                        <x-heroicon-o-x-circle class="h-4 w-4" />
                                        Cancelar
                                    </button>
                                @else
                                    <span class="rounded-xl bg-slate-50 py-2.5 text-center text-sm font-semibold text-slate-400 dark:bg-slate-900/40 dark:text-slate-500">
                                        Reserva cancelada
                                    </span>
                                @endif
                                <button wire:click="editReservation({{ $reserva['id'] }})" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]">
                                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                                    Editar
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="py-14 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-amber-300 bg-amber-100 text-amber-600 dark:border-[#fbbc48]/20 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">
                        <x-heroicon-o-calendar class="h-8 w-8" />
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ count($this->reservations) > 0 ? 'No hay reservas con esos filtros' : 'No hay reservas registradas' }}</h3>
                    <p class="mt-1 text-base text-slate-500 dark:text-slate-400">{{ count($this->reservations) > 0 ? 'Ajustá la búsqueda o los filtros de fecha/estado.' : 'Creá una reserva para empezar a gestionar tu salón.' }}</p>
                </div>
            @endif
        </section>

        {{-- ============ MODAL: NUEVA RESERVA (inline, con ReservationService) ============ --}}
        @if($modalNuevaReserva)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm" x-transition.opacity>
            <div x-on:click.outside="$wire.set('modalNuevaReserva', false)" class="w-full max-w-2xl rounded-2xl border border-amber-500/60 bg-white p-6 shadow-2xl dark:border-[#fbbc48]/40 dark:bg-[#0d0d0d]">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <h3 class="flex items-center gap-2 text-xl font-bold text-slate-900 dark:text-white">
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                        Nueva Reserva
                    </h3>
                    <button wire:click="$set('modalNuevaReserva', false)" class="p-1 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form wire:submit="crearReserva" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Cliente</label>
                        <select
                            wire:model="nuevaCustomerId"
                            class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white {{ $errors->has('nuevaCustomerId') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                        >
                            <option value="">— Elegí un cliente —</option>
                            @foreach($this->clientes as $cliente)
                                <option value="{{ $cliente['id'] }}">{{ $cliente['name'] }}</option>
                            @endforeach
                        </select>
                        @error('nuevaCustomerId')
                            <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Mesa</label>
                        <select
                            wire:model="nuevaTableId"
                            class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white {{ $errors->has('nuevaTableId') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                        >
                            <option value="">— Elegí una mesa —</option>
                            @foreach($this->mesasReservables as $mesa)
                                <option value="{{ $mesa['id'] }}">{{ $mesa['label'] }}</option>
                            @endforeach
                        </select>
                        @error('nuevaTableId')
                            <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Fecha y Hora</label>
                            <input
                                type="datetime-local"
                                wire:model="nuevaFecha"
                                min="{{ now()->format('Y-m-d\TH:i') }}"
                                class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white dark:[color-scheme:dark] {{ $errors->has('nuevaFecha') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                            />
                            @error('nuevaFecha')
                                <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Personas</label>
                            <input
                                type="number"
                                wire:model="nuevaPersonas"
                                min="1"
                                max="20"
                                class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white {{ $errors->has('nuevaPersonas') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                            />
                            @error('nuevaPersonas')
                                <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex gap-2 pt-3">
                        <button type="button" wire:click="$set('modalNuevaReserva', false)" class="flex-1 rounded-lg bg-slate-100 py-2.5 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" class="flex-1 rounded-lg bg-amber-500 py-2.5 text-sm font-black text-white transition-colors hover:bg-amber-400 dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]">Guardar Reserva</button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- ============ MODAL: EDITAR RESERVA (inline Stitch, ReservationService) ============ --}}
        @if($reservaEditId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm" wire:click.self="$set('reservaEditId', null)">
            <div class="w-full max-w-2xl rounded-2xl border border-amber-500/60 bg-white p-6 shadow-2xl dark:border-[#fbbc48]/40 dark:bg-[#0d0d0d]">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <h3 class="flex items-center gap-2 text-xl font-bold text-slate-900 dark:text-white">
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                        Editar Reserva #{{ $reservaEditId }}
                    </h3>
                    <button wire:click="$set('reservaEditId', null)" class="p-1 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">✕</button>
                </div>

                <form wire:submit="guardarEdicionReserva" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Cliente</label>
                        <select
                            wire:model="editCustomerId"
                            class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white {{ $errors->has('editCustomerId') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                        >
                            <option value="">— Elegí un cliente —</option>
                            @foreach($this->clientes as $cliente)
                                <option value="{{ $cliente['id'] }}" @selected($editCustomerId == $cliente['id'])>{{ $cliente['name'] }}</option>
                            @endforeach
                        </select>
                        @error('editCustomerId')
                            <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Mesa</label>
                        <select
                            wire:model="editTableId"
                            class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white {{ $errors->has('editTableId') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                        >
                            <option value="">— Elegí una mesa —</option>
                            @foreach($this->mesasReservables as $mesa)
                                <option value="{{ $mesa['id'] }}" @selected($editTableId == $mesa['id'])>{{ $mesa['label'] }}</option>
                            @endforeach
                        </select>
                        @error('editTableId')
                            <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Fecha y Hora</label>
                            <input
                                type="datetime-local"
                                wire:model="editFecha"
                                class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white dark:[color-scheme:dark] {{ $errors->has('editFecha') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                            />
                            @error('editFecha')
                                <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Personas</label>
                            <input
                                type="number"
                                wire:model="editPersonas"
                                min="1"
                                max="20"
                                class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white {{ $errors->has('editPersonas') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }}"
                            />
                            @error('editPersonas')
                                <p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-base font-semibold text-slate-700 dark:text-slate-300">Estado</label>
                        <select
                            wire:model="editStatus"
                            class="w-full rounded-lg border bg-white p-4 text-base text-slate-900 focus:border-amber-500 focus:outline-none dark:bg-[#111111] dark:text-white"
                        >
                            <option value="pending" @selected($editStatus === 'pending')>Pendiente</option>
                            <option value="confirmed" @selected($editStatus === 'confirmed')>Confirmada</option>
                            <option value="cancelled" @selected($editStatus === 'cancelled')>Cancelada</option>
                        </select>
                    </div>

                    <div class="flex gap-2 pt-3">
                        <button type="button" wire:click="$set('reservaEditId', null)" class="flex-1 rounded-lg bg-slate-100 py-2.5 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" class="flex-1 rounded-lg bg-amber-500 py-2.5 text-sm font-black text-white transition-colors hover:bg-amber-400 dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
</x-filament-panels::page>