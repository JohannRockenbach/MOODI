@if (!empty($suggestions))
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($suggestions as $index => $suggestion)
            <div class="fi-section-content-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="p-6">
                    <!-- Header con nombre de la receta -->
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $suggestion['name'] ?? 'Receta Desconocida' }}
                            </h3>
                        </div>
                        
                        @if (isset($suggestion['suggested_price']) && $suggestion['suggested_price'] > 0)
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm font-medium text-green-600 dark:text-green-400">
                                    Precio sugerido: ${{ number_format($suggestion['suggested_price'], 0, ',', '.') }}
                                </p>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Información del ingrediente crítico -->
                    <div class="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                        <p class="text-xs font-semibold text-amber-900 dark:text-amber-300 mb-1 uppercase tracking-wide">
                            🔥 Ingrediente Principal
                        </p>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $suggestion['ingredient_name'] ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <!-- Botones de acción con componentes Filament -->
                    <div class="flex flex-col gap-2">
                        <x-filament::button
                            wire:click="useRecipe({{ $index }})"
                            color="info"
                            icon="heroicon-o-light-bulb"
                            size="sm"
                            class="w-full justify-center"
                        >
                            💡 Usar esta Receta
                        </x-filament::button>
                        
                        <x-filament::button
                            wire:click="publishTemporalProduct({{ $index }})"
                            color="success"
                            icon="heroicon-o-arrow-up-tray"
                            size="sm"
                            class="w-full justify-center"
                        >
                            📢 Publicar en Menú
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="fi-section-content-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="p-8 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <svg class="h-8 w-8 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <p class="text-base font-medium text-gray-950 dark:text-white mb-2">
                No hay recetas sugeridas en este momento
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                El Chef Inteligente analiza el inventario cada hora para crear nuevas recetas con ingredientes próximos a vencer
            </p>
        </div>
    </div>
@endif

