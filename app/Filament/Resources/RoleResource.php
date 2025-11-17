<?php

namespace App\Filament\Resources;

use BezhanSalleh\FilamentShield\Resources\RoleResource as BaseRoleResource;
use BezhanSalleh\FilamentShield\Resources\RoleResource\Pages as BasePages;
use App\Filament\Resources\RoleResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class RoleResource extends BaseRoleResource
{
    /**
     * Filtrar roles críticos que no deben ser editados desde el panel.
     * Excluye: super_admin y cliente
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotIn('name', ['super_admin', 'cliente']);
    }

    public static function getPages(): array
    {
        return [
            'index' => BasePages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => BasePages\ViewRole::route('/{record}'),
            'edit' => BasePages\EditRole::route('/{record}/edit'),
        ];
    }
}
