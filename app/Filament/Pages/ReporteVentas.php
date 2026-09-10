<?php

namespace App\Filament\Pages;

class ReporteVentas extends Reports
{
    protected static string $view = 'filament.pages.reportes.ventas';

    protected static ?string $title = 'Reporte de Ventas';

    protected static ?string $navigationLabel = 'Ventas';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 1;

    protected string $activeSection = 'ventas';
}