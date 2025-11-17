<div>
    <select 
        wire:model.live="status" 
        @if($status === 'completed') disabled @endif
        class="
            fi-select-input
            block w-full border-gray-300 rounded-lg
            focus:border-primary-500 focus:ring focus:ring-primary-200
            dark:border-gray-600 dark:bg-gray-700 dark:text-white
            @if($status === 'pending') bg-yellow-50 dark:bg-yellow-900/20 @endif
            @if($status === 'processing') bg-blue-50 dark:bg-blue-900/20 @endif
            @if($status === 'completed') bg-green-50 dark:bg-green-900/20 cursor-not-allowed @endif
        "
    >
        <option value="pending">🟡 Pendiente</option>
        <option value="processing">🔵 En Proceso</option>
        <option value="completed">🟢 Completado</option>
    </select>
</div>
