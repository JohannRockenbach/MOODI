<x-filament-panels::page>
    {{-- NUEVA VERSIÓN DEL MAPA DE MESAS --}}
    
    {{-- Estadísticas --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div style="background-color: white; border: 4px solid #facc15; border-radius: 0.75rem; padding: 1.5rem;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <p style="font-size: 0.875rem; font-weight: 700; color: #4b5563; text-transform: uppercase; margin-bottom: 0.25rem;">Disponibles</p>
                    <p style="font-size: 3rem; font-weight: 900; color: #eab308;">{{ $stats['available'] }}</p>
                </div>
                <div style="background-color: #facc15; border-radius: 0.75rem; padding: 1rem;">
                    <x-heroicon-o-check-circle style="width: 3rem; height: 3rem; color: black;" />
                </div>
            </div>
        </div>

        <div style="background-color: black; border: 4px solid #facc15; border-radius: 0.75rem; padding: 1.5rem;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <p style="font-size: 0.875rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.25rem;">Ocupadas</p>
                    <p style="font-size: 3rem; font-weight: 900; color: #facc15;">{{ $stats['occupied'] }}</p>
                </div>
                <div style="background-color: #facc15; border-radius: 0.75rem; padding: 1rem;">
                    <x-heroicon-o-fire style="width: 3rem; height: 3rem; color: black;" />
                </div>
            </div>
        </div>

        <div style="background-color: #facc15; border: 4px solid black; border-radius: 0.75rem; padding: 1.5rem;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <p style="font-size: 0.875rem; font-weight: 700; color: black; text-transform: uppercase; margin-bottom: 0.25rem;">Reservadas</p>
                    <p style="font-size: 3rem; font-weight: 900; color: black;">{{ $stats['reserved'] }}</p>
                </div>
                <div style="background-color: black; border-radius: 0.75rem; padding: 1rem;">
                    <x-heroicon-o-clock style="width: 3rem; height: 3rem; color: #facc15;" />
                </div>
            </div>
        </div>
    </div>

    {{-- Mesas por Ubicación --}}
    @foreach($tablesByLocation as $location => $tables)
        <div style="margin-bottom: 2rem; background-color: white; border-radius: 0.75rem; padding: 1.5rem; border: 4px solid #facc15;">
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #facc15;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="background-color: #facc15; border-radius: 0.5rem; padding: 0.75rem;">
                        <x-heroicon-o-map-pin style="width: 2rem; height: 2rem; color: black;" />
                    </div>
                    <h2 style="font-size: 1.875rem; font-weight: 900; color: black; text-transform: uppercase;">{{ $location }}</h2>
                </div>
                <div style="background-color: #facc15; border-radius: 0.5rem; padding: 0.5rem 1rem;">
                    <span style="font-size: 1.25rem; font-weight: 700; color: black;">{{ count($tables) }} mesas</span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                @foreach($tables as $table)
                    <div 
                        wire:click="openTable({{ $table['id'] }})"
                        style="
                            position: relative;
                            border-radius: 0.75rem;
                            padding: 1rem;
                            cursor: pointer;
                            border: 4px solid;
                            @if($table['status'] === 'available')
                                background-color: white;
                                border-color: #facc15;
                            @elseif($table['status'] === 'occupied')
                                background-color: black;
                                border-color: #facc15;
                            @else
                                background-color: #facc15;
                                border-color: black;
                            @endif
                        "
                    >
                        @if($table['orders_count'] > 0)
                            <div style="position: absolute; top: -0.5rem; right: -0.5rem; background-color: #facc15; border: 4px solid black; border-radius: 50%; width: 3rem; height: 3rem; display: flex; align-items: center; justify-content: center; font-size: 1.125rem; font-weight: 900; color: black; z-index: 10;">
                                {{ $table['orders_count'] }}
                            </div>
                        @endif

                        <div style="text-align: center; margin-bottom: 0.75rem;">
                            <div style="
                                font-size: 4rem;
                                font-weight: 900;
                                @if($table['status'] === 'available')
                                    color: black;
                                @elseif($table['status'] === 'occupied')
                                    color: #facc15;
                                @else
                                    color: black;
                                @endif
                            ">
                                {{ $table['number'] }}
                            </div>
                        </div>

                        <div style="text-align: center; margin-bottom: 0.75rem;">
                            <div style="
                                display: inline-flex;
                                align-items: center;
                                gap: 0.5rem;
                                padding: 0.25rem 0.75rem;
                                border-radius: 0.5rem;
                                font-size: 0.875rem;
                                font-weight: 700;
                                @if($table['status'] === 'available')
                                    background-color: #f3f4f6;
                                    color: #374151;
                                @elseif($table['status'] === 'occupied')
                                    background-color: rgba(250, 204, 21, 0.2);
                                    color: #facc15;
                                @else
                                    background-color: rgba(0, 0, 0, 0.2);
                                    color: black;
                                @endif
                            ">
                                <x-heroicon-o-user-group style="width: 1rem; height: 1rem;" />
                                <span>{{ $table['capacity'] }}</span>
                            </div>
                        </div>

                        <div style="text-align: center; margin-bottom: 0.5rem;">
                            @if($table['status'] === 'available')
                                <span style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 900; background-color: #facc15; color: black;">
                                    <x-heroicon-o-check-circle style="width: 1rem; height: 1rem;" />
                                    DISPONIBLE
                                </span>
                            @elseif($table['status'] === 'occupied')
                                <span style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 900; background-color: #facc15; color: black; animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;">
                                    <x-heroicon-o-fire style="width: 1rem; height: 1rem;" />
                                    OCUPADA
                                </span>
                            @else
                                <span style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 900; background-color: black; color: #facc15;">
                                    <x-heroicon-o-clock style="width: 1rem; height: 1rem;" />
                                    RESERVADA
                                </span>
                            @endif
                        </div>

                        @if($table['status'] === 'occupied' && $table['orders_count'] > 0)
                            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 2px solid rgba(250, 204, 21, 0.3); text-align: center;">
                                @if($table['elapsed_time'])
                                    <div style="font-size: 0.75rem; font-weight: 700; color: #facc15; display: flex; align-items: center; justify-content: center; gap: 0.25rem; margin-bottom: 0.25rem;">
                                        <x-heroicon-o-clock style="width: 1rem; height: 1rem;" />
                                        {{ $table['elapsed_time'] }}
                                    </div>
                                @endif
                                @if($table['total_amount'] > 0)
                                    <div style="font-size: 1.25rem; font-weight: 900; color: #facc15;">
                                        ${{ number_format($table['total_amount'], 0, ',', '.') }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if(count($tablesByLocation) === 0)
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 5rem 0; text-align: center;">
            <h3 style="font-size: 1.875rem; font-weight: 900; color: black; margin-bottom: 0.75rem;">
                No hay mesas registradas
            </h3>
            <p style="font-size: 1.125rem; color: #6b7280;">
                Crea mesas desde el módulo de configuración para empezar a gestionar tu salón.
            </p>
        </div>
    @endif

    {{-- MODAL --}}
    <x-filament::modal id="table-details" width="4xl">
        @if($this->selectedTable)
            @php $table = $this->selectedTable; @endphp

            <x-slot name="heading">
                <div class="flex items-center gap-4">
                    <div class="w-20 h-20 rounded-xl flex items-center justify-center font-black text-3xl shadow-lg border-4
                        @if($table['status'] === 'available')
                            bg-white border-yellow-400 text-gray-900
                        @elseif($table['status'] === 'occupied')
                            bg-black border-yellow-400 text-yellow-400
                        @else
                            bg-yellow-400 border-black text-black
                        @endif
                    ">
                        {{ $table['number'] }}
                    </div>
                    <div class="flex-1">
                        <h3 class="text-3xl font-black text-gray-900 dark:text-white">Mesa #{{ $table['number'] }}</h3>
                        <p class="text-lg text-gray-600 dark:text-gray-400 font-semibold">
                            📍 {{ $table['location'] }} • 👥 {{ $table['capacity'] }} personas
                        </p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-6">
                @if(count($table['orders']) > 0)
                    <div>
                        <h4 class="text-2xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                            <div class="bg-yellow-400 rounded-xl p-2">
                                <x-heroicon-o-shopping-bag class="w-6 h-6 text-black" />
                            </div>
                            Pedidos Activos ({{ count($table['orders']) }})
                        </h4>
                        
                        <div class="space-y-4">
                            @foreach($table['orders'] as $order)
                                <div class="bg-white dark:bg-gray-800 border-4 border-yellow-400 rounded-xl p-6">
                                    <div class="flex justify-between items-center mb-4 pb-4 border-b-2 border-yellow-400">
                                        <div class="flex items-center gap-3">
                                            <div class="bg-black text-yellow-400 rounded-lg px-4 py-2 text-xl font-black border-2 border-yellow-400">
                                                #{{ $order['id'] }}
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 dark:text-gray-400 font-bold">{{ $order['created_at'] }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-4 space-y-2">
                                        @foreach($order['products'] as $product)
                                            <div class="flex justify-between items-center p-2">
                                                <span class="text-base font-bold">
                                                    <span class="inline-flex items-center justify-center w-8 h-8 bg-yellow-400 text-black rounded-lg text-sm font-black mr-2">{{ $product['quantity'] }}</span>
                                                    {{ $product['name'] }}
                                                </span>
                                                <span class="text-lg font-black">${{ number_format($product['price'] * $product['quantity'], 0, ',', '.') }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="flex justify-between items-center pt-4 border-t-2 border-yellow-400">
                                        <span class="text-xl font-black">Subtotal</span>
                                        <span class="text-3xl font-black text-yellow-500">${{ number_format($order['total'], 0, ',', '.') }}</span>
                                    </div>

                                    <button 
                                        wire:click="editOrder({{ $order['id'] }})"
                                        class="mt-4 w-full bg-black hover:bg-gray-800 text-yellow-400 font-black py-4 px-6 rounded-lg border-2 border-yellow-400 flex items-center justify-center gap-2">
                                        <x-heroicon-o-pencil-square class="w-6 h-6" />
                                        Editar Pedido
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 bg-black text-yellow-400 rounded-xl p-8 border-4 border-yellow-400">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-lg font-bold mb-1">TOTAL MESA</p>
                                    <p class="text-sm opacity-75">{{ count($table['orders']) }} {{ count($table['orders']) === 1 ? 'pedido' : 'pedidos' }}</p>
                                </div>
                                <span class="text-6xl font-black">${{ number_format($table['total_amount'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center border-4 border-dashed border-yellow-400">
                        <h4 class="text-2xl font-black mb-2">Mesa sin pedidos</h4>
                        <p class="text-lg">Esta mesa está lista para recibir clientes</p>
                    </div>
                @endif
            </div>

            <x-slot name="footerActions">
                <div class="flex flex-col md:flex-row gap-4 w-full">
                    <button
                        wire:click="createOrderForTable"
                        class="flex-1 bg-yellow-400 hover:bg-yellow-500 text-black font-black py-4 px-6 rounded-lg border-4 border-black text-xl flex items-center justify-center gap-2">
                        <x-heroicon-o-plus-circle class="w-7 h-7" />
                        ➕ Agregar Pedido
                    </button>

                    @if(count($table['orders']) > 0)
                        <button
                            wire:click="prepareCobroMesa({{ $table['id'] }})"
                            x-on:click="$dispatch('open-modal', { id: 'cobrar-mesa' })"
                            class="flex-1 bg-green-500 hover:bg-green-600 text-white font-black py-4 px-6 rounded-lg border-4 border-yellow-400 text-xl flex items-center justify-center gap-2 shadow-lg">
                            <x-heroicon-o-currency-dollar class="w-7 h-7" />
                            💰 Cobrar Mesa
                        </button>

                        <button
                            wire:click="freeTable({{ $table['id'] }})"
                            wire:confirm="¿Liberar la mesa {{ $table['number'] }}?"
                            class="flex-1 bg-red-500 hover:bg-red-600 text-white font-black py-4 px-6 rounded-lg border-4 border-black text-xl flex items-center justify-center gap-2 shadow-lg">
                            <x-heroicon-o-arrow-right-start-on-rectangle class="w-7 h-7" />
                            🔓 Liberar Mesa
                        </button>
                    @endif
                                </div>
            </x-slot>
        @endif
    </x-filament::modal>

    {{-- MODAL DE COBRO --}}
    <x-filament::modal id="cobrar-mesa" width="2xl">
        <x-slot name="heading">
            <div class="flex items-center gap-3">
                <div class="bg-green-500 rounded-xl p-3">
                    <x-heroicon-o-currency-dollar class="w-8 h-8 text-white" />
                </div>
                <h3 class="text-2xl font-black text-gray-900 dark:text-white">Cobrar Mesa</h3>
            </div>
        </x-slot>

        <div class="space-y-6">
            {{-- Total sin descuento --}}
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 border-4 border-yellow-400">
                <div class="flex justify-between items-center">
                    <span class="text-lg font-bold text-gray-700 dark:text-gray-300">Subtotal</span>
                    <span class="text-3xl font-black text-gray-900 dark:text-white">
                        ${{ number_format($totalAmount, 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- Método de Pago --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">
                    💳 Método de Pago
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <button 
                        wire:click="$set('paymentMethod', 'cash')"
                        class="flex flex-col items-center justify-center p-4 rounded-lg border-4 font-bold transition-all
                            {{ $paymentMethod === 'cash' ? 'bg-green-500 border-black text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-green-500' }}">
                        <x-heroicon-o-banknotes class="w-8 h-8 mb-2" />
                        Efectivo
                    </button>
                    <button 
                        wire:click="$set('paymentMethod', 'card')"
                        class="flex flex-col items-center justify-center p-4 rounded-lg border-4 font-bold transition-all
                            {{ $paymentMethod === 'card' ? 'bg-green-500 border-black text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-green-500' }}">
                        <x-heroicon-o-credit-card class="w-8 h-8 mb-2" />
                        Tarjeta
                    </button>
                    <button 
                        wire:click="$set('paymentMethod', 'transfer')"
                        class="flex flex-col items-center justify-center p-4 rounded-lg border-4 font-bold transition-all
                            {{ $paymentMethod === 'transfer' ? 'bg-green-500 border-black text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-green-500' }}">
                        <x-heroicon-o-arrows-right-left class="w-8 h-8 mb-2" />
                        Transferencia
                    </button>
                </div>
            </div>

            {{-- Descuentos --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">
                    🏷️ Aplicar Descuentos (Opcional)
                </label>
                @php
                    $discounts = \App\Models\Discount::where('is_active', true)->get();
                @endphp
                @if($discounts->count() > 0)
                    <div class="space-y-2 max-h-48 overflow-y-auto bg-white dark:bg-gray-800 rounded-lg border-2 border-gray-300 p-3">
                        @foreach($discounts as $discount)
                            <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-2 border-transparent hover:border-yellow-400 transition-all">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectedDiscounts" 
                                    value="{{ $discount->id }}"
                                    class="w-5 h-5 text-yellow-400 border-gray-300 rounded focus:ring-yellow-400">
                                <div class="flex-1">
                                    <div class="font-bold text-gray-900 dark:text-white">{{ $discount->name }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $discount->type === 'percentage' ? $discount->value . '%' : '$' . number_format($discount->value, 0, ',', '.') }} de descuento
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4 text-gray-500 italic">
                        No hay descuentos disponibles
                    </div>
                @endif
            </div>

            {{-- Descuento aplicado --}}
            @if($discountAmount > 0)
                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 border-2 border-yellow-400">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-yellow-800 dark:text-yellow-300">Descuento Total</span>
                        <span class="text-2xl font-black text-yellow-600 dark:text-yellow-400">
                            -${{ number_format($discountAmount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            @endif

            {{-- Total final --}}
            <div class="bg-black rounded-lg p-6 border-4 border-yellow-400">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-lg font-bold text-yellow-400 mb-1">TOTAL A COBRAR</p>
                        <p class="text-sm text-yellow-400/75">{{ ucfirst($paymentMethod === 'cash' ? 'Efectivo' : ($paymentMethod === 'card' ? 'Tarjeta' : 'Transferencia')) }}</p>
                    </div>
                    <span class="text-5xl font-black text-yellow-400">
                        ${{ number_format(max(0, $totalAmount - $discountAmount), 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        <x-slot name="footerActions">
            <div class="flex gap-3 w-full">
                <button
                    x-on:click="$dispatch('close-modal', { id: 'cobrar-mesa' })"
                    class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-black py-3 px-6 rounded-lg text-lg">
                    Cancelar
                </button>
                <button
                    wire:click="cobrarMesa"
                    wire:loading.attr="disabled"
                    class="flex-1 bg-green-500 hover:bg-green-600 text-white font-black py-3 px-6 rounded-lg border-4 border-yellow-400 text-lg flex items-center justify-center gap-2">
                    <x-heroicon-o-check-circle class="w-6 h-6" />
                    <span wire:loading.remove>Confirmar Cobro</span>
                    <span wire:loading>Procesando...</span>
                </button>
            </div>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
```
