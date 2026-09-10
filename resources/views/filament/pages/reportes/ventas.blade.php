<x-filament-panels::page>
    <div class="space-y-6">
        @include('filament.pages.reportes.filtros')

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total facturado</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">$ {{ number_format($this->getReportData()['total_amount'], 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ventas</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $this->getReportData()['count'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ticket promedio</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">$ {{ number_format($this->getReportData()['average'], 2, ',', '.') }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ventas por día</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Fecha</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Ventas</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->getReportData()['by_day'] as $day)
                            <tr>
                                <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $day['label'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ $day['count'] }}</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-900 dark:text-gray-100">$ {{ number_format($day['total'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Sin ventas en el rango seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Por método de pago</h3>
            <div class="mt-4 space-y-3">
                @forelse ($this->getReportData()['by_method'] as $method)
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 dark:bg-gray-800">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $method['method'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $method['count'] }} venta(s)</p>
                        </div>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">$ {{ number_format($method['total'], 2, ',', '.') }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin datos.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>