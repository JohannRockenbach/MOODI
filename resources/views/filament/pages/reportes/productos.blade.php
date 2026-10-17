<x-filament-panels::page>
    <div class="space-y-6">
        @include('filament.pages.reportes.filtros')

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Top 10 productos</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Producto</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Cant.</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->getReportData()['top_products'] as $product)
                            <tr>
                                <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $product['name'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ $product['quantity'] }}</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-900 dark:text-gray-100">$ {{ number_format($product['total'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Sin datos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Más vendidos</h3>
                <div class="mt-4 space-y-2">
                    @forelse ($this->getProductReport()['top'] as $i => $product)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-gray-800">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $i + 1 }}. {{ $product['name'] }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $product['quantity'] }} u · $ {{ number_format($product['total'], 2, ',', '.') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin datos.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Menos vendidos</h3>
                <div class="mt-4 space-y-2">
                    @forelse ($this->getProductReport()['bottom'] as $i => $product)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-gray-800">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $i + 1 }}. {{ $product['name'] }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $product['quantity'] }} u · $ {{ number_format($product['total'], 2, ',', '.') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin datos.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>