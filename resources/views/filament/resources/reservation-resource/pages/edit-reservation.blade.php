<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    {{-- ============ HEADER STITCH: Restaurante El Fogón · Editar Reserva ============ --}}
    @php
        $reserva = $this->record;
        $reserva->loadMissing(['customer', 'table']);
        $statusBadge = match ($reserva->status) {
            'confirmed' => 'bg-green-500/15 text-green-700 border-green-500/40 dark:text-green-400 dark:border-green-500/40',
            'cancelled' => 'bg-rose-500/15 text-rose-700 border-rose-500/40 dark:text-rose-400 dark:border-rose-500/40',
            default => 'bg-amber-500/15 text-amber-700 border-amber-500/40 dark:text-[#fbbc48] dark:border-[#fbbc48]/40',
        };
        $statusLabel = match ($reserva->status) {
            'confirmed' => 'Confirmada',
            'cancelled' => 'Cancelada',
            default => 'Pendiente',
        };
    @endphp

    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-[#272a31] dark:bg-[#1c2026]">
        {{-- Barra superior: marca + volver al padre --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-[#272a31]">
            <div class="flex items-center gap-3">
                <div class="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-2xl bg-amber-500 shadow-md shadow-amber-500/30 dark:bg-[#fbbc48] dark:text-[#422c00]">
                    <span class="text-[10px] font-black uppercase leading-none tracking-wider text-white/90 dark:text-[#422c00]/80">{{ $reserva->reservation_time->locale('es')->translatedFormat('M') }}</span>
                    <span class="text-2xl font-black leading-none text-white dark:text-[#422c00]">{{ $reserva->reservation_time->format('d') }}</span>
                </div>
                <div>
                    <span class="block text-lg font-bold uppercase tracking-wider text-slate-900 dark:text-[#e0e2ec]">Restaurante El Fogón</span>
                    <span class="block text-sm font-semibold text-amber-600 dark:text-[#fbbc48]">Editar Reserva #{{ $reserva->id }}</span>
                </div>
            </div>
            <a
                href="{{ \App\Filament\Pages\GestionReservas::getUrl() }}"
                class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-200 dark:bg-[#0b0e15] dark:text-[#c1c6d5] dark:hover:bg-[#272a31]"
            >
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Volver a Reservas
            </a>
        </div>

        {{-- Datos de la reserva: cliente, mesa, fecha, personas, estado --}}
        <div class="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#0b0e15]">
                <span class="block text-xs uppercase tracking-wider font-semibold text-slate-500 dark:text-[#c1c6d5]">Cliente</span>
                <span class="mt-1 block truncate text-sm font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $reserva->customer?->name ?? 'Sin cliente' }}</span>
            </div>
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#0b0e15]">
                <span class="block text-xs uppercase tracking-wider font-semibold text-slate-500 dark:text-[#c1c6d5]">Mesa</span>
                <span class="mt-1 block text-sm font-bold text-amber-600 dark:text-[#fbbc48]">Mesa #{{ $reserva->table?->number ?? 'N/A' }}</span>
            </div>
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#0b0e15]">
                <span class="block text-xs uppercase tracking-wider font-semibold text-slate-500 dark:text-[#c1c6d5]">Fecha y Hora</span>
                <span class="mt-1 block text-sm font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $reserva->reservation_time->format('d/m/Y H:i') }}</span>
            </div>
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#0b0e15]">
                <span class="block text-xs uppercase tracking-wider font-semibold text-slate-500 dark:text-[#c1c6d5]">Estado</span>
                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusBadge }}">
                    <span class="h-2 w-2 rounded-full {{ $reserva->status === 'confirmed' ? 'bg-green-500' : ($reserva->status === 'cancelled' ? 'bg-rose-500' : 'bg-amber-500 animate-pulse') }}"></span>
                    {{ $statusLabel }}
                </span>
            </div>
        </div>
    </div>

    {{-- ============ FORMULARIO (misma lógica/validaciones del recurso) ============ --}}
    @capture($form)
        <x-filament-panels::form
            id="form"
            :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
            wire:submit="save"
        >
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>
    @endcapture

    @php
        $relationManagers = $this->getRelationManagers();
        $hasCombinedRelationManagerTabsWithContent = $this->hasCombinedRelationManagerTabsWithContent();
    @endphp

    @if ((! $hasCombinedRelationManagerTabsWithContent) || (! count($relationManagers)))
        {{ $form() }}
    @endif

    @if (count($relationManagers))
        <x-filament-panels::resources.relation-managers
            :active-locale="isset($activeLocale) ? $activeLocale : null"
            :active-manager="$this->activeRelationManager ?? ($hasCombinedRelationManagerTabsWithContent ? null : array_key_first($relationManagers))"
            :content-tab-label="$this->getContentTabLabel()"
            :content-tab-icon="$this->getContentTabIcon()"
            :content-tab-position="$this->getContentTabPosition()"
            :managers="$relationManagers"
            :owner-record="$record"
            :page-class="static::class"
        >
            @if ($hasCombinedRelationManagerTabsWithContent)
                <x-slot name="content">
                    {{ $form() }}
                </x-slot>
            @endif
        </x-filament-panels::resources.relation-managers>
    @endif

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>