<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasReportData;
use Filament\Pages\Page;

class ReporteProductos extends Page
{
    use HasReportData;

    protected static string $view = 'filament.pages.reportes.productos';

    protected static ?string $title = 'Reporte de Productos';

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $navigationParentItem = 'Reportes';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole('super_admin');
    }
}