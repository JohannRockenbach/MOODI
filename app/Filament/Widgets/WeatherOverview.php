<?php

namespace App\Filament\Widgets;

use App\Services\WeatherService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherOverview extends BaseWidget
{
    /**
     * Orden del widget en el dashboard
     */
    protected static ?int $sort = 1;

    /**
     * Intervalo de refresco del widget (en segundos)
     * Cada 5 minutos (300 segundos)
     */
    protected static ?string $pollingInterval = '300s';

    /**
     * Obtener las estadísticas del clima
     */
    protected function getStats(): array
    {
        try {
            // Usar WeatherService con app() en lugar de constructor para evitar errores de Livewire
            $weatherService = app(WeatherService::class);
            $weather = $weatherService->getCurrentWeather();

            // Si la API no responde
            if (!$weather) {
                Log::warning('Weather API no disponible en el widget');
                return [
                    Stat::make('Clima', 'API no responde')
                        ->description('⚠️ Verifica tu conexión a internet o intenta más tarde')
                        ->descriptionIcon('heroicon-o-exclamation-triangle')
                        ->color('warning'),
                ];
            }

            // Extraer datos del objeto 'current'
            $current = $weather['current'] ?? null;
            
            if (!$current) {
                Log::warning('Weather API devolvió datos incompletos', ['weather' => $weather]);
                return [
                    Stat::make('Clima', 'Datos incompletos')
                        ->description('⚠️ La API devolvió una respuesta inesperada')
                        ->descriptionIcon('heroicon-o-exclamation-triangle')
                        ->color('warning'),
                ];
            }

            $temperature = $current['temperature_2m'] ?? null;
            $isRaining = $weatherService->isRaining($weather);

            // Si no hay temperatura válida
            if ($temperature === null) {
                Log::warning('Weather API sin temperatura', ['current' => $current]);
                return [
                    Stat::make('Clima', 'Sin temperatura')
                        ->description('⚠️ No se pudo obtener la temperatura actual')
                        ->descriptionIcon('heroicon-o-exclamation-triangle')
                        ->color('warning'),
                ];
            }

            // Determinar el color según la temperatura
            if ($temperature > 30) {
                $tempColor = 'danger';
                $tempEmoji = '🔥';
                $tempText = 'Calor extremo';
            } elseif ($temperature > 25) {
                $tempColor = 'warning';
                $tempEmoji = '☀️';
                $tempText = 'Cálido';
            } elseif ($temperature > 15) {
                $tempColor = 'success';
                $tempEmoji = '🌤️';
                $tempText = 'Agradable';
            } else {
                $tempColor = 'info';
                $tempEmoji = '❄️';
                $tempText = 'Fresco';
            }

            // Condición climática
            $condition = $isRaining ? '🌧️ Lluvia detectada' : '☀️ Sin precipitaciones';
            
            // Tarjeta ÚNICA consolidada: Temperatura + Condición
            return [
                Stat::make('Clima en Apóstoles', round($temperature) . '°C')
                    ->description($tempEmoji . ' ' . $tempText . ' • ' . $condition)
                    ->descriptionIcon($isRaining ? 'heroicon-o-cloud' : 'heroicon-o-sun')
                    ->color($tempColor)
                    ->icon('heroicon-o-sun'),
            ];
        } catch (\Exception $e) {
            Log::error('Error inesperado en WeatherOverview widget', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                Stat::make('Error', 'Error del sistema')
                    ->description('❌ ' . $e->getMessage())
                    ->descriptionIcon('heroicon-o-x-circle')
                    ->color('danger'),
            ];
        }
    }
}
