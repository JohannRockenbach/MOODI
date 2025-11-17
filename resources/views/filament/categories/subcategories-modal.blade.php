@php
    /** @var \App\Models\Category $category */
    /** @var \Illuminate\Database\Eloquent\Collection $subcategories */
@endphp

<div class="space-y-4">
    @if($subcategories->isEmpty())
        <div class="text-center py-8 text-gray-500">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
            </svg>
            <p class="mt-2 text-sm">No hay subcategorías</p>
        </div>
    @else
        <div class="grid gap-4">
            @foreach($subcategories as $subcategory)
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $subcategory->name }}
                                </h3>
                            </div>
                            
                            @if($subcategory->description)
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    {{ $subcategory->description }}
                                </p>
                            @endif

                            <div class="flex items-center gap-4 text-sm">
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 rounded">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <span class="font-medium">{{ $subcategory->products->count() }}</span>
                                    <span>producto{{ $subcategory->products->count() !== 1 ? 's' : '' }}</span>
                                </span>

                                @if($subcategory->display_order !== null)
                                    <span class="text-gray-500 dark:text-gray-400">
                                        Orden: {{ $subcategory->display_order }}
                                    </span>
                                @endif
                            </div>

                            @if($subcategory->products->isNotEmpty())
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Productos en esta subcategoría:</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($subcategory->products as $product)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-xs">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                                </svg>
                                                {{ $product->name }}
                                                @if($product->price)
                                                    <span class="text-primary-600 dark:text-primary-400 font-medium">
                                                        ($ {{ number_format($product->price, 2, ',', '.') }})
                                                    </span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                <span>
                    Total: <strong class="text-gray-900 dark:text-gray-100">{{ $subcategories->count() }}</strong> subcategoría{{ $subcategories->count() !== 1 ? 's' : '' }}
                </span>
                <span>
                    <strong class="text-gray-900 dark:text-gray-100">{{ $subcategories->sum(fn($s) => $s->products->count()) }}</strong> producto{{ $subcategories->sum(fn($s) => $s->products->count()) !== 1 ? 's' : '' }} en total
                </span>
            </div>
        </div>
    @endif
</div>
