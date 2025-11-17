<x-filament-panels::page>
    <style>
        /* Resetear estilos de Filament */
        .kitchen-container * {
            box-sizing: border-box;
        }
        
        .order-card {
            transition: transform 0.2s;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
        }

        /* Asegurar que las columnas estén lado a lado */
        .kitchen-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 1rem !important;
            height: 100% !important;
        }

        .column-container {
            display: flex !important;
            flex-direction: column !important;
            min-height: 0 !important;
        }

        .orders-list {
            flex: 1 !important;
            overflow-y: auto !important;
            max-height: calc(100vh - 250px) !important;
        }
    </style>

    <div wire:poll.5s="loadOrders" class="kitchen-container -m-6">
        <div class="kitchen-grid p-4">
            
            {{-- COLUMNA IZQUIERDA --}}
            <div class="column-container">
                <div style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0;">📋 PENDIENTES</h2>
                    <span style="background: white; color: #dc2626; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 700; font-size: 0.875rem;">
                        {{ $pendingOrders->count() }}
                    </span>
                </div>

                <div class="orders-list" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    @forelse ($pendingOrders as $order)
                        @php
                            $style = match($order->type) {
                                'salon' => ['bg' => '#3b82f6', 'icon' => '🏠', 'label' => 'MESA ' . ($order->table->number ?? 'N/A')],
                                'delivery' => ['bg' => '#10b981', 'icon' => '🏍️', 'label' => 'DELIVERY'],
                                'para_llevar' => ['bg' => '#f59e0b', 'icon' => '📦', 'label' => 'PARA LLEVAR'],
                                default => ['bg' => '#6b7280', 'icon' => '📄', 'label' => 'PEDIDO']
                            };
                        @endphp

                        <div class="order-card" style="background: {{ $style['bg'] }}; color: white; border-radius: 0.5rem; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.15);" wire:key="pend-{{ $order->id }}">
                            
                            {{-- Header --}}
                            <div style="background: rgba(0,0,0,0.15); padding: 0.5rem 0.75rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2);">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-size: 1.25rem;">{{ $style['icon'] }}</span>
                                    <span style="font-weight: 900; font-size: 1rem;">#{{ $order->id }}</span>
                                    <span style="font-size: 0.75rem; font-weight: 600;">{{ $style['label'] }}</span>
                                </div>
                                <span style="font-size: 0.75rem; background: rgba(255,255,255,0.25); padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-weight: 600;">
                                    {{ $order->created_at->format('H:i') }}
                                </span>
                            </div>

                            {{-- Productos --}}
                            <div style="padding: 0.5rem 0.75rem; display: flex; flex-direction: column; gap: 0.25rem;">
                                @foreach ($order->orderProducts->where('product.category.name', '!=', 'Bebidas') as $item)
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="background: rgba(0,0,0,0.3); color: white; font-weight: 900; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; min-width: 2rem; text-align: center;">
                                            {{ $item->quantity }}x
                                        </span>
                                        <span style="font-weight: 600; font-size: 0.875rem;">
                                            {{ $item->product->name ?? 'Producto' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Botón --}}
                            <div style="padding: 0 0.75rem 0.5rem;">
                                <button 
                                    wire:click="startProcessing({{ $order->id }})"
                                    wire:loading.attr="disabled"
                                    style="width: 100%; background: white; color: #1f2937; font-weight: 900; font-size: 0.875rem; padding: 0.5rem; border-radius: 0.375rem; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.25rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.2s;"
                                    onmouseover="this.style.background='#f3f4f6'"
                                    onmouseout="this.style.background='white'">
                                    ▶️ EMPEZAR
                                </button>
                            </div>
                        </div>
                    @empty
                        <div style="background: #f3f4f6; padding: 2rem; text-align: center; border-radius: 0.5rem; color: #6b7280;">
                            ✅ No hay pedidos pendientes
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- COLUMNA DERECHA --}}
            <div class="column-container">
                <div style="background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%); color: white; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0;">🔥 EN PREPARACIÓN</h2>
                    <span style="background: white; color: #eab308; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 700; font-size: 0.875rem;">
                        {{ $processingOrders->count() }}
                    </span>
                </div>

                <div class="orders-list" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    @forelse ($processingOrders as $order)
                        @php
                            $style = match($order->type) {
                                'salon' => ['bg' => '#3b82f6', 'icon' => '🏠', 'label' => 'MESA ' . ($order->table->number ?? 'N/A')],
                                'delivery' => ['bg' => '#10b981', 'icon' => '🏍️', 'label' => 'DELIVERY'],
                                'para_llevar' => ['bg' => '#f59e0b', 'icon' => '📦', 'label' => 'PARA LLEVAR'],
                                default => ['bg' => '#6b7280', 'icon' => '📄', 'label' => 'PEDIDO']
                            };
                        @endphp

                        <div class="order-card" style="background: {{ $style['bg'] }}; color: white; border-radius: 0.5rem; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.15);" wire:key="proc-{{ $order->id }}">
                            
                            {{-- Header --}}
                            <div style="background: rgba(0,0,0,0.15); padding: 0.5rem 0.75rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2);">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-size: 1.25rem;">{{ $style['icon'] }}</span>
                                    <span style="font-weight: 900; font-size: 1rem;">#{{ $order->id }}</span>
                                    <span style="font-size: 0.75rem; font-weight: 600;">{{ $style['label'] }}</span>
                                </div>
                                <span style="font-size: 0.75rem; background: rgba(255,255,255,0.25); padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-weight: 600;">
                                    {{ $order->created_at->format('H:i') }}
                                </span>
                            </div>

                            {{-- Productos --}}
                            <div style="padding: 0.5rem 0.75rem; display: flex; flex-direction: column; gap: 0.25rem;">
                                @foreach ($order->orderProducts->where('product.category.name', '!=', 'Bebidas') as $item)
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="background: rgba(0,0,0,0.3); color: white; font-weight: 900; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; min-width: 2rem; text-align: center;">
                                            {{ $item->quantity }}x
                                        </span>
                                        <span style="font-weight: 600; font-size: 0.875rem;">
                                            {{ $item->product->name ?? 'Producto' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Botón --}}
                            <div style="padding: 0 0.75rem 0.5rem;">
                                <button 
                                    wire:click="markAsReady({{ $order->id }})"
                                    wire:loading.attr="disabled"
                                    style="width: 100%; background: white; color: #16a34a; font-weight: 900; font-size: 0.875rem; padding: 0.5rem; border-radius: 0.375rem; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.25rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.2s;"
                                    onmouseover="this.style.background='#f0fdf4'"
                                    onmouseout="this.style.background='white'">
                                    ✅ LISTO
                                </button>
                            </div>
                        </div>
                    @empty
                        <div style="background: #f3f4f6; padding: 2rem; text-align: center; border-radius: 0.5rem; color: #6b7280;">
                            🍳 No hay pedidos en preparación
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-filament-panels::page>
