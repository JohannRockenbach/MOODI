<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Reportes</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Selecciona un reporte. También están disponibles en el menú lateral bajo "Reportes".
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <a href="{{ \App\Filament\Pages\ReporteVentas::getUrl() }}"
               class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-orange-300 transition dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-chart-bar-square class="h-8 w-8 text-orange-500" />
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100">Ventas</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Totales, por día y por método de pago.</p>
                    </div>
                </div>
            </a>

            <a href="{{ \App\Filament\Pages\ReporteCaja::getUrl() }}"
               class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-orange-300 transition dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-beaker class="h-8 w-8 text-orange-500" />
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100">Caja</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Cierres de caja del período.</p>
                    </div>
                </div>
            </a>

            <a href="{{ \App\Filament\Pages\ReporteProductos::getUrl() }}"
               class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-orange-300 transition dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-shopping-bag class="h-8 w-8 text-orange-500" />
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100">Productos</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Más y menos vendidos.</p>
                    </div>
                </div>
            </a>

            <a href="{{ \App\Filament\Pages\ReporteGanancias::getUrl() }}"
               class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-orange-300 transition dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-calendar class="h-8 w-8 text-orange-500" />
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100">Ganancias</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Ingresos mensuales y semanales.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</x-filament-panels::page>