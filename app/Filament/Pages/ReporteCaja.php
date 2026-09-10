<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasReportData;
use Filament\Pages\Page;

class ReporteCaja extends Page
{
    use HasReportData;

    protected static string $view = 'filament.pages.reportes.caja';

    protected static ?string $title = 'Reporte de Caja';

    protected static ?string $navigationLabel = 'Caja';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $navigationParentItem = 'Reportes';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole('super_admin');
    }
}