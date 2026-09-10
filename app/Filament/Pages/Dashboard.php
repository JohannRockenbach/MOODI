<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Escritorio';

    protected static ?int $navigationSort = 1;

    /**
     * El Escritorio es la vista operativa general: no corresponde al Cocinero,
     * que entra directo a la pantalla de cocina.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ! $user->hasRole('Cocinero');
    }
}