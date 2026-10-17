<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Desde</label>
            <input type="date" wire:model.live="from"
                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hasta</label>
            <input type="date" wire:model.live="to"
                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Método de pago</label>
            <select wire:model.live="paymentMethod"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Todos</option>
                @foreach ($paymentMethods as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-wrap gap-3">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                <input type="checkbox" wire:model.live="includeAnnulled" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                Incluir anuladas
            </label>
            <x-filament::button type="button" icon="heroicon-m-arrow-down-tray" wire:click="exportCsv" size="sm">
                Exportar CSV
            </x-filament::button>
        </div>
    </div>
</div>