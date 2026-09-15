<div>
    <select 
        wire:model.live="status" 
        @if(in_array($status, ['completed', 'cancelled'])) disabled @endif
        class="
            fi-select
            block w-full border-gray-300 rounded-lg
            focus:border-primary-500 focus:ring focus:ring-primary-200
            dark:border-gray-600 dark:bg-gray-700 dark:text-white
            @if($status === 'pending') bg-yellow-50 dark:bg-yellow-900/20 @endif
            @if($status === 'processing') bg-blue-50 dark:bg-blue-900/20 @endif
            @if($status === 'ready_for_pickup') bg-orange-50 dark:bg-orange-900/20 @endif
            @if($status === 'completed') bg-green-50 dark:bg-green-900/20 cursor-not-allowed @endif
            @if($status === 'cancelled') bg-red-50 dark:bg-red-900/20 cursor-not-allowed @endif
        "
    >
        @php
            $allStatuses = [
                'pending' => '🟡 Pendiente',
                'processing' => '🔵 En Proceso',
                'ready_for_pickup' => '🟠 Listo para Retirar',
                'completed' => '🟢 Completado',
                'cancelled' => '🔴 Cancelado',
            ];
        @endphp
        
        @foreach($allowedStatuses as $statusValue)
            <option value="{{ $statusValue }}">{{ $allStatuses[$statusValue] ?? $statusValue }}</option>
        @endforeach
    </select>
</div>
