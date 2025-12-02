<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Models\Reservation;
use App\Models\Table;
use App\Filament\Resources\SaleResource;
use App\Filament\Resources\ReservationResource;
use App\Filament\Pages\TableMap;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // ========================================
        // Ventas de la Caja Abierta (no de "hoy")
        // ========================================
        // Primero, buscar si hay una caja abierta
        $caja = \App\Models\Caja::where('status', 'abierta')
            ->where('restaurant_id', 1)
            ->first();

        if ($caja) {
            // Si hay caja abierta, sumar las ventas pagadas de esa caja
            $ventasHoy = $caja->sales()
                ->where('status', 'paid')
                ->sum('total_amount');
            
            $cantidadVentas = $caja->sales()
                ->where('status', 'paid')
                ->count();
            
            $descripcion = "{$cantidadVentas} venta" . ($cantidadVentas != 1 ? 's' : '') . " en la caja actual";
        } else {
            // Si NO hay caja abierta, ventas = 0
            $ventasHoy = 0;
            $cantidadVentas = 0;
            $descripcion = "No hay caja abierta actualmente";
        }

        // Reservas pendientes
        $reservasPendientes = Reservation::where('status', 'pending')
            ->count();

        // Mesas ocupadas
        $mesasOcupadas = Table::where('status', 'occupied')
            ->count();

        return [
            Stat::make('Ventas de Hoy', '$ ' . number_format($ventasHoy, 2, ',', '.'))
                ->description($descripcion)
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color($caja ? 'success' : 'gray')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3])
                ->url(SaleResource::getUrl('index')),

            Stat::make('Reservas Pendientes', $reservasPendientes)
                ->description('Reservas por confirmar')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning')
                ->url(ReservationResource::getUrl('index')),

            Stat::make('Mesas Ocupadas', $mesasOcupadas)
                ->description('Mesas actualmente en uso')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->url(TableMap::getUrl()),
        ];
    }
}
