@php
    /** @var \App\Models\Category $category */
    /** @var \Illuminate\Database\Eloquent\Collection $products */
@endphp

<div class="space-y-4">
    @if($products->isEmpty())
        <div class="text-center py-12">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            <p class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">No hay productos</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">Esta categoría aún no tiene productos asignados</p>
        </div>
    @else
        <div class="mb-4 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <span class="text-sm font-semibold text-primary-900 dark:text-primary-100">
                        Total: {{ $products->count() }} producto{{ $products->count() !== 1 ? 's' : '' }}
                    </span>
                </div>
                <span class="text-sm font-bold text-primary-700 dark:text-primary-300">
                    Total: $ {{ number_format($products->sum('price'), 2, ',', '.') }}
                </span>
            </div>
        </div>

        <div class="grid gap-3">
            @foreach($products as $product)
                <div class="group relative border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-primary-300 dark:hover:border-primary-600 hover:shadow-md transition-all duration-200">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <!-- Nombre del producto -->
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-primary-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">
                                    {{ $product->name }}
                                </h3>
                                @if($product->is_available ?? true)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                        Disponible
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                        No disponible
                                    </span>
                                @endif
                            </div>
                            
                            <!-- Descripción -->
                            @if($product->description)
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                                    {{ $product->description }}
                                </p>
                            @endif

                            <!-- Información adicional -->
                            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                @if($product->sku)
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                        </svg>
                                        SKU: {{ $product->sku }}
                                    </span>
                                @endif

                                @if(isset($product->stock))
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                        Stock: {{ $product->stock }}
                                    </span>
                                @endif

                                @if($product->created_at)
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ $product->created_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Precio -->
                        <div class="flex-shrink-0 text-right">
                            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                $ {{ number_format($product->price, 2, ',', '.') }}
                            </div>
                            @if(isset($product->cost) && $product->cost > 0)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Costo: $ {{ number_format($product->cost, 2, ',', '.') }}
                                </div>
                                <div class="text-xs font-medium text-green-600 dark:text-green-400">
                                    Margen: {{ number_format((($product->price - $product->cost) / $product->cost) * 100, 1) }}%
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Resumen final -->
        <div class="mt-6 pt-4 border-t-2 border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <p class="text-gray-600 dark:text-gray-400 mb-1">Total de productos</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $products->count() }}</p>
                </div>
                <div class="p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
                    <p class="text-primary-700 dark:text-primary-300 mb-1">Valor total</p>
                    <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                        $ {{ number_format($products->sum('price'), 2, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
