<div style="display:flex; align-items:center; gap:12px;">
    <div style="font-size: 1.125rem; font-weight: 700; color: #16a34a;">
        @if(is_null($balance))
            <span style="color: #6b7280;">-</span>
        @else
            <span>$ {{ number_format($balance, 2, ',', '.') }}</span>
        @endif
    </div>
    <button 
        wire:click="refreshBalance" 
        wire:loading.attr="disabled"
        type="button"
        class="inline-flex items-center justify-center gap-1 font-medium rounded-lg border transition-colors outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2rem] px-3 text-sm text-gray-800 bg-white border-gray-300 hover:bg-gray-50 focus:ring-primary-600 dark:text-gray-200 dark:bg-gray-800 dark:border-gray-600 dark:hover:bg-gray-700"
        style="min-width: 2.5rem; height: 2.5rem; padding: 0.5rem;"
    >
        <svg wire:loading.remove wire:target="refreshBalance" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        <svg wire:loading wire:target="refreshBalance" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </button>
</div>
