<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-500" />
                <span>Stock Bajo - Alerta de Inventario</span>
            </div>
        </x-slot>

        @php
            $items = $this->getLowStockData();
            $criticalItems = array_filter($items, fn($item) => $item['current_stock'] <= 0 || $item['difference'] <= -5);
            $warningItems = array_filter($items, fn($item) => !in_array($item, $criticalItems));
        @endphp

        @if(empty($items))
            <div class="text-center py-8">
                <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500 dark:text-success-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                    ¡Todo en orden!
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No hay productos o ingredientes con stock bajo.
                </p>
            </div>
        @else
            {{-- Banner de Alertas Críticas --}}
            @if(count($criticalItems) > 0)
                <div class="mb-4 rounded-lg bg-danger-50 dark:bg-danger-900/20 p-4 border-l-4 border-danger-500">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-exclamation-circle class="h-8 w-8 text-danger-600 dark:text-danger-400 animate-pulse" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-bold text-danger-800 dark:text-danger-200">
                                ⚠️ ALERTA CRÍTICA: Stock Agotado o Muy Bajo
                            </h3>
                            <div class="mt-2 text-sm text-danger-700 dark:text-danger-300">
                                <p class="font-semibold">
                                    {{ count($criticalItems) }} {{ count($criticalItems) === 1 ? 'ítem tiene' : 'ítems tienen' }} stock crítico (≤0 o muy por debajo del mínimo)
                                </p>
                                <ul class="mt-2 list-disc list-inside space-y-1">
                                    @foreach(array_slice($criticalItems, 0, 3) as $item)
                                        <li>
                                            <strong>{{ $item['name'] }}</strong>: 
                                            Stock actual {{ number_format($item['current_stock'], 2, ',', '.') }}
                                            {{ $item['measurement_unit'] ?? '' }}
                                            (Mínimo: {{ number_format($item['min_stock'], 2, ',', '.') }})
                                        </li>
                                    @endforeach
                                    @if(count($criticalItems) > 3)
                                        <li class="text-danger-600 dark:text-danger-400 font-semibold">
                                            Y {{ count($criticalItems) - 3 }} más...
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif(count($items) > 0)
                <div class="mb-4 rounded-lg bg-warning-50 dark:bg-warning-900/20 p-4 border-l-4 border-warning-500">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-warning-800 dark:text-warning-200">
                                Advertencia: Stock Bajo
                            </h3>
                            <div class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                                <p>{{ count($items) }} {{ count($items) === 1 ? 'ítem está' : 'ítems están' }} por debajo del stock mínimo.</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-left divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Nombre
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Stock Actual
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Stock Mínimo
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Diferencia
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Tipo
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Restaurante
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($items as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if($item['type'] === 'Ingrediente')
                                            <x-heroicon-o-beaker class="w-5 h-5 mr-2 text-info-500" />
                                        @else
                                            <x-heroicon-o-cube class="w-5 h-5 mr-2 text-success-500" />
                                        @endif
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $item['name'] }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    @php
                                        $stockClass = 'text-gray-900 dark:text-gray-100';
                                        if ($item['current_stock'] <= 0) {
                                            $stockClass = 'text-danger-600 dark:text-danger-400 font-bold';
                                        } elseif ($item['current_stock'] <= $item['min_stock'] * 0.5) {
                                            $stockClass = 'text-warning-600 dark:text-warning-400 font-semibold';
                                        }
                                        
                                        $formatted = number_format((float) $item['current_stock'], 2, ',', '.');
                                        if ($item['type'] === 'Ingrediente' && $item['measurement_unit']) {
                                            $formatted .= ' ' . $item['measurement_unit'];
                                        }
                                    @endphp
                                    <span class="text-sm {{ $stockClass }}">
                                        {{ $formatted }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    @php
                                        $formatted = number_format((float) $item['min_stock'], 2, ',', '.');
                                        if ($item['type'] === 'Ingrediente' && $item['measurement_unit']) {
                                            $formatted .= ' ' . $item['measurement_unit'];
                                        }
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200">
                                        {{ $formatted }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    @php
                                        $diff = $item['difference'];
                                        $badgeClass = $diff < 0 ? 'bg-danger-100 dark:bg-danger-900 text-danger-800 dark:text-danger-200' : 'bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-200';
                                        $formatted = number_format(abs((float) $diff), 2, ',', '.');
                                        $display = $diff < 0 ? "-{$formatted}" : $formatted;
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                        {{ $display }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    @php
                                        $badgeClass = $item['type'] === 'Ingrediente' 
                                            ? 'bg-info-100 dark:bg-info-900 text-info-800 dark:text-info-200'
                                            : 'bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-200';
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                        {{ $item['type'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $item['restaurant_name'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                <p>Total de ítems con stock bajo: <span class="font-semibold">{{ count($items) }}</span></p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
