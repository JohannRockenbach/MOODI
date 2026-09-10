<?php

namespace App\Filament\Pages;

class ReporteCaja extends Reports
{
    protected static string $view = 'filament.pages.reportes.caja';

    protected static ?string $title = 'Reporte de Caja';

    protected static ?string $navigationLabel = 'Caja';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 2;

    protected string $activeSection = 'caja';
}