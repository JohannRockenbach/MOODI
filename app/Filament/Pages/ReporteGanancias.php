<?php

namespace App\Filament\Pages;

class ReporteGanancias extends Reports
{
    protected static string $view = 'filament.pages.reportes.ganancias';

    protected static ?string $title = 'Reporte de Ganancias';

    protected static ?string $navigationLabel = 'Ganancias';

    protected static ?string $navigationIcon = 'heroicon-o-trending-up';

    protected static ?int $navigationSort = 4;

    protected string $activeSection = 'ganancias';
}