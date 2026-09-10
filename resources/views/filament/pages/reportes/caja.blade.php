<x-filament-panels::page>
    <div class="space-y-6">
        @include('filament.pages.reportes.filtros')

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Cierre de Caja</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Caja</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Estado</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Apertura</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Ventas</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Cierre</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->getCajaReport() as $caja)
                            <tr>
                                <td class="px-4 py-2 text-gray-900 dark:text-gray-100">Caja #{{ $caja['id'] }} · {{ $caja['opened_at'] }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold {{ $caja['status'] === 'Abierta' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ $caja['status'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">$ {{ number_format($caja['opening_amount'], 2, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ $caja['sales_count'] }} · $ {{ number_format($caja['sales_total'], 2, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-900 dark:text-gray-100">$ {{ number_format($caja['closing_amount'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Sin cajas en el rango seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>