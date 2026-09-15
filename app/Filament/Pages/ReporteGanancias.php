<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasReportData;
use Filament\Pages\Page;

class ReporteGanancias extends Page
{
    use HasReportData;

    protected static string $view = 'filament.pages.reportes.ganancias';

    protected static ?string $title = 'Reporte de Ganancias';

    protected static ?string $navigationLabel = 'Ganancias';

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $navigationParentItem = 'Reportes';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole('super_admin');
    }
}