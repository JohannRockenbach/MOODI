<?php

namespace App\Filament\Widgets;

use App\Services\WeatherService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class WeatherOverview extends BaseWidget
{
    /**
     * Intervalo de refresco del widget (en segundos)
     * Null = no auto-refresh
     */
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        // Obtener instancia del servicio
        $weatherService = app(WeatherService::class);

        // Cachear datos del clima por 15 minutos (900 segundos)
        // Esto evita llamar a la API en cada refresh
        $data = Cache::remember('weather_apostoles', 900, function () use ($weatherService) {
            return $weatherService->getCurrentWeather();
        });

        // Si no hay datos, mostrar tarjetas con error
        if (!$data) {
            return [
                Stat::make('Temperatura Actual', 'N/A')
                    ->description('Error al obtener datos')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger'),
                
                Stat::make('Condición', 'Sin datos')
                    ->description('Intenta recargar en unos minutos')
                    ->color('warning'),
            ];
        }

        // Extraer datos del clima
        $temp = $data['current']['temperature_2m'] ?? 0;
        $raining = $weatherService->isRaining($data);

        // Determinar icono según temperatura
        $icon = $temp > 20 ? 'heroicon-o-sun' : 'heroicon-o-cloud';

        // Determinar color según temperatura
        $color = match (true) {
            $temp > 30 => 'danger',
            $temp >= 20 => 'warning',
            default => 'info',
        };

        return [
            // Tarjeta 1: Temperatura
            Stat::make('Temperatura Actual', "{$temp} °C")
                ->icon($icon)
                ->color($color)
                ->description('Apóstoles, Misiones'),

            // Tarjeta 2: Condición de lluvia
            Stat::make('Condición', $raining ? 'Lluvioso 🌧️' : 'Sin Lluvia ☀️')
                ->color($raining ? 'primary' : 'success')
                ->description('Datos de tiempo (Apóstoles)')
                ->icon($raining ? 'heroicon-o-cloud-arrow-down' : 'heroicon-o-sun'),
        ];
    }
}
