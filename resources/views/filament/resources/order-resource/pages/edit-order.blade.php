<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    {{-- ============ HEADER STITCH: Restaurante El Fogón · Editar Pedido ============ --}}
    @php
        $order = $this->record;
        $order->loadMissing(['table', 'waiter', 'orderProducts.product']);
        $badge = match ($order->type) {
            'salon' => ['class' => 'bg-amber-500 text-white dark:bg-[#fbbc48] dark:text-[#422c00]', 'label' => 'MESA #' . ($order->table?->number ?? 'N/A')],
            'para_llevar' => ['class' => 'bg-amber-100 text-amber-700 dark:bg-[#fbbc48]/15 dark:text-[#fbbc48]', 'label' => '🥡 PARA LLEVAR'],
            'delivery' => ['class' => 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400', 'label' => '🛵 DELIVERY'],
            default => ['class' => 'bg-slate-100 text-slate-600 dark:bg-[#272a31] dark:text-[#c1c6d5]', 'label' => '📄 PEDIDO'],
        };
        $statusBadge = match ($order->status) {
            'pending' => 'bg-amber-500/15 text-amber-700 border-amber-500/40 dark:text-[#fbbc48] dark:border-[#fbbc48]/40',
            'processing' => 'bg-sky-500/15 text-sky-700 border-sky-500/40 dark:text-sky-400 dark:border-sky-500/40',
            'ready_for_pickup' => 'bg-green-500/15 text-green-700 border-green-500/40 dark:text-green-400 dark:border-green-500/40',
            'completed' => 'bg-emerald-500/15 text-emerald-700 border-emerald-500/40 dark:text-emerald-400 dark:border-emerald-500/40',
            default => 'bg-rose-500/15 text-rose-700 border-rose-500/40 dark:text-rose-400 dark:border-rose-500/40',
        };
        $total = $order->orderProducts->sum(fn ($item) => (float) $item->quantity * (float) $item->price);
    @endphp

    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-[#272a31] dark:bg-[#1c2026]">
        {{-- Barra superior: marca + volver al padre --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-[#272a31]">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 text-xl shadow-md shadow-amber-500/30 dark:bg-[#fbbc48]">
                    <span class="leading-none">🧾</span>
                </div>
                <div>
                    <span class="block text-lg font-bold uppercase tracking-wider text-slate-900 dark:text-[#e0e2ec]">Restaurante El Fogón</span>
                    <span class="block text-sm font-semibold text-amber-600 dark:text-[#fbbc48]">Editar Pedido #{{ $order->id }}</span>
                </div>
            </div>
            <a
                href="{{ \App\Filament\Pages\GestionPedidos::getUrl() }}"
                class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-200 dark:bg-[#0b0e15] dark:text-[#c1c6d5] dark:hover:bg-[#272a31]"
            >
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Volver a Pedidos
            </a>
        </div>

        {{-- Datos del pedido: tipo, estado, mesa/cliente, total --}}
        <div class="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#0b0e15]">
                <span class="block text-xs uppercase tracking-wider font-semibold text-slate-500 dark:text-[#c1c6d5]">Tipo</span>
                <span class="mt-1 inline-flex rounded-lg px-2.5 py-1 text-sm font-black tracking-wide {{ $badge['class'] }}">{{ $badge['label'] }}</span>
            </div>
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#0b0e15]">
                <span class="block text-xs uppercase tracking-wider font-semibold text-slate-500 dark:text-[#c1c6d5]">Estado</span>
                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusBadge }}">
                    <span class="h-2 w-2 rounded-full
                        {{ $order->status === 'pending' ? 'bg-amber-500' : ($order->status === 'processing' ? 'bg-sky-500 animate-pulse' : ($order->status === 'ready_for_pickup' ? 'bg-green-500' : ($order->status === 'completed' ? 'bg-emerald-500' : 'bg-rose-500'))) }}"></span>
                    {{ str($order->status)->replace(['pending', 'processing', 'ready_for_pickup', 'completed', 'cancelled'], ['Pendiente', 'En Preparación', 'Listo', 'Completado', 'Cancelado']) }}
                </span>
            </div>
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#0b0e15]">
                <span class="block text-xs uppercase tracking-wider font-semibold text-slate-500 dark:text-[#c1c6d5]">{{ $order->type === 'salon' ? 'Mesa / Mozo' : 'Cliente / Mozo' }}</span>
                <span class="mt-1 block truncate text-sm font-bold text-slate-900 dark:text-[#e0e2ec]">
                    {{ $order->type === 'salon' ? ($order->table?->number ? 'Mesa #'.$order->table->number : 'Sin mesa') : ($order->customer_name ?? 'Cliente') }}
                    · {{ $order->waiter?->name ?? 'Pedido Web' }}
                </span>
            </div>
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#0b0e15]">
                <span class="block text-xs uppercase tracking-wider font-semibold text-slate-500 dark:text-[#c1c6d5]">Total</span>
                <span class="mt-1 block text-2xl font-black text-emerald-600 dark:text-emerald-400">$ {{ number_format((float) $total, 2, ',', '.') }}</span>
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