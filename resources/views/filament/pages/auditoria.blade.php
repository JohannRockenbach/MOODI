<x-filament-panels::page class="fi-auditoria-page">
    <div class="mb-4 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                Registro de acciones críticas
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Vista de solo lectura: anulaciones, ventas, cierres de caja, precios y usuarios.
            </p>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>