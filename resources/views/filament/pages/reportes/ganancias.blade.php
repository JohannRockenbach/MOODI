<x-filament-panels::page>
    <div class="space-y-6">
        @include('filament.pages.reportes.filtros')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ingresos Mensuales (6 meses)</h3>
                <div class="mt-4 space-y-2">
                    @foreach ($this->getProfitReport()['months'] as $month)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-gray-800">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $month['label'] }}</p>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">$ {{ number_format($month['total'], 2, ',', '.') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ingresos Semanales (8 semanas)</h3>
                <div class="mt-4 space-y-2">
                    @foreach ($this->getProfitReport()['weeks'] as $week)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-gray-800">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $week['label'] }}</p>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">$ {{ number_format($week['total'], 2, ',', '.') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>