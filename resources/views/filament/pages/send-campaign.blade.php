<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}
        
        @if(!empty($subject) && !empty($body))
            <x-filament::section>
                <x-slot name="heading">
                    👀 Vista Previa del Email
                </x-slot>
                
                <div class="prose dark:prose-invert max-w-none">
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 border-2 border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold mb-4">{{ $subject }}</h3>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {!! $body !!}
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
