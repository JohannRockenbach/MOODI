<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Filtros del reporte --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <form wire:submit="exportCsv" class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Reporte de Ventas</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Ventas del restaurante con filtros por fecha, método de pago y estado. Exportable a CSV.
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Desde</label>
                        <input type="date" wire:model.live="from"
                               class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hasta</label>
                        <input type="date" wire:model.live="to"
                               class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Método de pago</label>
                        <select wire:model.live="paymentMethod"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Todos</option>
                            @foreach ($paymentMethods as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model.live="includeAnnulled" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                            Incluir anuladas
                        </label>
                    </div>
                </div>

                <div>
                    <x-filament::button type="submit" icon="heroicon-m-arrow-down-tray">
                        Exportar CSV
                    </x-filament::button>
                </div>
            </form>
        </div>

        {{-- Métricas principales --}}
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

        {{-- Ventas por día --}}
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

        {{-- Ventas por método y top productos --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
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
        </div>

        {{-- Cierre de Caja --}}
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

        {{-- Productos más y menos vendidos --}}
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

        {{-- Ganancias mensuales y semanales --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ingresos Mensuales (6 meses)</h3>
                <div class="mt-4 space-y-2">
                    @foreach ($this->getProfitReport()['months'] as $month)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-gray-800">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $month['label'] }}</p>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">$ {{ number_format($month['total'], 2, ',', '.') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ingresos Semanales (8 semanas)</h3>
                <div class="mt-4 space-y-2">
                    @foreach ($this->getProfitReport()['weeks'] as $week)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-gray-800">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $week['label'] }}</p>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">$ {{ number_format($week['total'], 2, ',', '.') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>