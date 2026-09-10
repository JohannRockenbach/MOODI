<?php

namespace App\Filament\Pages;

class ReporteProductos extends Reports
{
    protected static string $view = 'filament.pages.reportes.productos';

    protected static ?string $title = 'Reporte de Productos';

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 3;

    protected string $activeSection = 'productos';
}