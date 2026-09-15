<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasReportData;
use Filament\Pages\Page;

/**
 * Página padre "Reportes": aparece en la navegación y despliega los
 * sub-reportes (Ventas, Caja, Productos, Ganancias).
 */
class Reports extends Page
{
    use HasReportData;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'filament.pages.reportes.index';

    protected static ?string $title = 'Reportes';

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole('super_admin');
    }
}