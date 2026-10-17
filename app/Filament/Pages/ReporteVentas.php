<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasReportData;
use Filament\Pages\Page;

class ReporteVentas extends Page
{
    use HasReportData;

    protected static string $view = 'filament.pages.reportes.ventas';

    protected static ?string $title = 'Reporte de Ventas';

    protected static ?string $navigationLabel = 'Ventas';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $navigationParentItem = 'Reportes';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole('super_admin');
    }
}