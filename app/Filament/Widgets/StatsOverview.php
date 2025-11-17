<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Models\Reservation;
use App\Models\Table;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Ventas de hoy (solo ventas pagadas)
        $ventasHoy = Sale::whereDate('created_at', today())
            ->where('status', 'paid')
            ->sum('total_amount');
        
        // Cantidad de ventas de hoy
        $cantidadVentas = Sale::whereDate('created_at', today())
            ->where('status', 'paid')
            ->count();

        // Reservas pendientes
        $reservasPendientes = Reservation::where('status', 'pending')
            ->count();

        // Mesas ocupadas
        $mesasOcupadas = Table::where('status', 'occupied')
            ->count();

        return [
            Stat::make('Ventas de Hoy', '$ ' . number_format($ventasHoy, 2, ',', '.'))
                ->description("{$cantidadVentas} ventas realizadas hoy")
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Reservas Pendientes', $reservasPendientes)
                ->description('Reservas por confirmar')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning'),

            Stat::make('Mesas Ocupadas', $mesasOcupadas)
                ->description('Mesas actualmente en uso')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];
    }
}
