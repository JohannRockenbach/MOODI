@php
    /** @var \App\Models\Order $record */
@endphp

<div class="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-200 dark:border-gray-700">
    <!-- Encabezado -->
    <div class="mb-4 pb-3 border-b border-gray-300 dark:border-gray-600">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                Detalle del Pedido #{{ $record->id }}
            </h3>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-gray-600 dark:text-gray-400">
                    <strong class="text-gray-900 dark:text-gray-100">Mesa:</strong> 
                    {{ $record->table->number ?? 'N/A' }}
                </span>
                <span class="text-gray-600 dark:text-gray-400">
                    <strong class="text-gray-900 dark:text-gray-100">Mozo:</strong> 
                    {{ $record->waiter->name ?? $record->user->name ?? 'N/A' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Tabla de Productos -->
    @if($record->orderProducts->isEmpty())
        <div class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Este pedido aún no tiene productos</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Producto
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Cantidad
                        </th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Precio Unitario
                        </th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Subtotal
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($record->orderProducts as $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-primary-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $item->product->name ?? 'Producto no disponible' }}
                                        </div>
                                        @if(isset($item->notes) && $item->notes)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                <em>Nota: {{ $item->notes }}</em>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-primary-100 text-primary-800 dark:bg-primary-900/30 dark:text-primary-400">
                                    {{ $item->quantity }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-900 dark:text-gray-100">
                                $ {{ number_format($item->product->price ?? 0, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                                $ {{ number_format($item->quantity * ($item->product->price ?? 0), 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-gray-100 uppercase">
                            Total del Pedido:
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                                $ {{ number_format($record->orderProducts->sum(fn($item) => $item->quantity * ($item->product->price ?? 0)), 2, ',', '.') }}
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Información adicional -->
        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div class="text-center p-2 bg-gray-100 dark:bg-gray-800 rounded">
                    <p class="text-gray-600 dark:text-gray-400 text-xs mb-1">Total Productos</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        {{ $record->orderProducts->count() }}
                    </p>
                </div>
                <div class="text-center p-2 bg-gray-100 dark:bg-gray-800 rounded">
                    <p class="text-gray-600 dark:text-gray-400 text-xs mb-1">Total Items</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        {{ $record->orderProducts->sum('quantity') }}
                    </p>
                </div>
                <div class="text-center p-2 bg-primary-50 dark:bg-primary-900/20 rounded">
                    <p class="text-primary-700 dark:text-primary-300 text-xs mb-1">Estado</p>
                    <p class="text-sm font-bold text-primary-600 dark:text-primary-400">
                        {{ match($record->status) {
                            'en_proceso' => 'En Proceso',
                            'servido' => 'Servido',
                            'pagado' => 'Pagado',
                            'cancelado' => 'Cancelado',
                            default => ucfirst($record->status)
                        } }}
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
