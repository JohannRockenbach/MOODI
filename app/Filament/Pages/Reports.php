<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'filament.pages.reports';

    protected static ?string $title = 'Reportes';

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        // Reportes es una página administrativa: solo super_admin.
        $user = auth()->user();

        return $user !== null && $user->hasRole('super_admin');
    }
}
