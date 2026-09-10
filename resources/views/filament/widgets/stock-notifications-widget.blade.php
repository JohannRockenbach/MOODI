<x-filament-widgets::widget>
    @if($this->shouldShowNotification())
        @php
            $items = $this->getCriticalItems();
            $criticalCount = count(array_filter($items, fn($item) => $item['severity'] === 'critical'));
            $warningCount = count(array_filter($items, fn($item) => $item['severity'] === 'warning'));
            $uniqueId = uniqid();
        @endphp

        <div class="relative overflow-hidden rounded-lg shadow-lg border-4 border-danger-600 dark:border-danger-500">
            @if($criticalCount > 0)
                {{-- Alerta Crítica (Stock = 0 o muy bajo) --}}
                <div class="bg-gradient-to-r from-danger-600 to-danger-500 dark:from-danger-800 dark:to-danger-700 p-6">
                    {{-- Encuadre del título y botón --}}
                    <div class="border-2 border-white/30 rounded-lg p-4 bg-white/5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="relative">
                                        <x-heroicon-o-exclamation-circle class="h-16 w-16 text-white animate-pulse" />
                                        <span class="absolute top-0 right-0 block h-4 w-4 rounded-full bg-white animate-ping"></span>
                                    </div>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                                        🚨 ALERTA CRÍTICA DE STOCK
                                    </h2>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <button 
                                onclick="
                                    const details = document.getElementById('stock-details-{{ $uniqueId }}');
                                    const icon = document.getElementById('toggle-icon-{{ $uniqueId }}');
                                    const buttonText = document.getElementById('button-text-{{ $uniqueId }}');
                                    if (details.classList.contains('hidden')) {
                                        details.classList.remove('hidden');
                                        icon.style.transform = 'rotate(180deg)';
                                        buttonText.textContent = 'Ocultar Detalles';
                                    } else {
                                        details.classList.add('hidden');
                                        icon.style.transform = 'rotate(0deg)';
                                        buttonText.textContent = 'Ver Detalles';
                                    }
                                "
                                class="inline-flex items-center px-4 py-2 border-2 border-white rounded-md shadow-sm text-sm font-medium text-white hover:bg-white hover:text-danger-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white transition"
                            >
                                <span id="button-text-{{ $uniqueId }}">Ver Detalles</span>
                                <x-heroicon-o-chevron-down id="toggle-icon-{{ $uniqueId }}" class="ml-2 h-4 w-4 transition-transform duration-300" />
                            </button>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Lista COMPLETA de ítems con stock bajo (colapsable) --}}
                    <div id="stock-details-{{ $uniqueId }}" class="hidden mt-4 space-y-3">
                        <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-white/20">
                            <h3 class="text-lg font-semibold text-white mb-3">📋 Lista Completa de Productos con Stock Bajo</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-white/20">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-white uppercase tracking-wider">Nombre</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-white uppercase tracking-wider">Tipo</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-white uppercase tracking-wider">Stock Actual</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-white uppercase tracking-wider">Stock Mínimo</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-white uppercase tracking-wider">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/10">
                                        @foreach($items as $item)
                                            <tr class="hover:bg-white/5 transition">
                                                <td class="px-3 py-3 text-sm font-medium text-white">{{ $item['name'] }}</td>
                                                <td class="px-3 py-3 text-sm {{ $item['current_stock'] <= 0 ? 'text-danger-50' : 'text-warning-300' }}">{{ $item['type'] }}</td>
                                                <td class="px-3 py-3 text-sm text-white">
                                                    {{ number_format($item['current_stock'], 2, ',', '.') }}{{ $item['measurement_unit'] ? ' ' . $item['measurement_unit'] : '' }}
                                                </td>
                                                <td class="px-3 py-3 text-sm text-white">
                                                    {{ number_format($item['min_stock'], 2, ',', '.') }}{{ $item['measurement_unit'] ? ' ' . $item['measurement_unit'] : '' }}
                                                </td>
                                                <td class="px-3 py-3 text-sm">
                                                    @if($item['current_stock'] <= 0)
                                                        <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-danger fi-badge-color-danger bg-danger-50 text-danger-600 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
                                                            AGOTADO
                                                        </span>
                                                    @else
                                                        <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 bg-primary-50 text-primary-600 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                                            BAJO
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($warningCount > 0)
                {{-- Advertencia (Stock bajo pero no crítico) --}}
                <div class="bg-gradient-to-r from-warning-500 to-warning-400 dark:from-warning-700 dark:to-warning-600 p-6">
                    {{-- Encuadre del título y botón --}}
                    <div class="border-2 border-warning-700/40 dark:border-white/30 rounded-lg p-4 bg-white/10 dark:bg-white/5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <x-heroicon-o-exclamation-triangle class="h-12 w-12 text-warning-950 dark:text-white" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-warning-950 dark:text-white">
                                        ⚠️ Advertencia: Stock Bajo
                                    </h2>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <button 
                                onclick="
                                    const details = document.getElementById('stock-details-{{ $uniqueId }}');
                                    const icon = document.getElementById('toggle-icon-{{ $uniqueId }}');
                                    const buttonText = document.getElementById('button-text-{{ $uniqueId }}');
                                    if (details.classList.contains('hidden')) {
                                        details.classList.remove('hidden');
                                        icon.style.transform = 'rotate(180deg)';
                                        buttonText.textContent = 'Ocultar Detalles';
                                    } else {
                                        details.classList.add('hidden');
                                        icon.style.transform = 'rotate(0deg)';
                                        buttonText.textContent = 'Ver Detalles';
                                    }
                                "
                                class="inline-flex items-center px-4 py-2 border-2 border-warning-900 dark:border-white rounded-md shadow-sm text-sm font-medium text-warning-950 dark:text-white hover:bg-white hover:text-warning-700 dark:hover:bg-warning-500 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-warning-700 dark:focus:ring-white transition"
                            >
                                <span id="button-text-{{ $uniqueId }}">Ver Detalles</span>
                                <x-heroicon-o-chevron-down id="toggle-icon-{{ $uniqueId }}" class="ml-2 h-4 w-4 transition-transform duration-300" />
                            </button>
                            </div>
                        </div>
                    </div>

                    {{-- Lista COMPLETA de ítems con stock bajo (colapsable) --}}
                    <div id="stock-details-{{ $uniqueId }}" class="hidden mt-4 space-y-3">
                        <div class="bg-white/20 dark:bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-warning-900/20 dark:border-white/20">
                            <h3 class="text-lg font-semibold text-warning-950 dark:text-white mb-3">📋 Lista Completa de Productos con Stock Bajo</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-warning-950/20 dark:divide-white/20">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-warning-950 dark:text-white uppercase tracking-wider">Nombre</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-warning-950 dark:text-white uppercase tracking-wider">Tipo</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-warning-950 dark:text-white uppercase tracking-wider">Stock Actual</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-warning-950 dark:text-white uppercase tracking-wider">Stock Mínimo</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-warning-950 dark:text-white uppercase tracking-wider">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-warning-950/10 dark:divide-white/10">
                                        @foreach($items as $item)
                                            <tr class="hover:bg-warning-900/10 dark:hover:bg-white/5 transition">
                                                <td class="px-3 py-3 text-sm font-medium text-warning-950 dark:text-white">{{ $item['name'] }}</td>
                                                <td class="px-3 py-3 text-sm text-warning-900 dark:text-warning-200">{{ $item['type'] }}</td>
                                                <td class="px-3 py-3 text-sm text-warning-950 dark:text-white">
                                                    {{ number_format($item['current_stock'], 2, ',', '.') }}{{ $item['measurement_unit'] ? ' ' . $item['measurement_unit'] : '' }}
                                                </td>
                                                <td class="px-3 py-3 text-sm text-warning-950 dark:text-white">
                                                    {{ number_format($item['min_stock'], 2, ',', '.') }}{{ $item['measurement_unit'] ? ' ' . $item['measurement_unit'] : '' }}
                                                </td>
                                                <td class="px-3 py-3 text-sm">
                                                    @if($item['current_stock'] <= 0)
                                                        <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-danger fi-badge-color-danger bg-danger-50 text-danger-600 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
                                                            AGOTADO
                                                        </span>
                                                    @else
                                                        <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 bg-primary-50 text-primary-600 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                                            BAJO
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Barra animada en la parte inferior --}}
            <div class="h-1 bg-white/30 overflow-hidden">
                <div class="h-full bg-white animate-pulse"></div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
