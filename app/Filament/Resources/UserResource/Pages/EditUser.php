<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Evita el auto-lockout del panel:
     * el select de roles excluye 'super_admin' de sus opciones, por lo que al
     * guardar el propio perfil se removería el rol y el usuario perdería acceso.
     * Si el registro editado es el usuario autenticado con rol super_admin,
     * se re-agrega el rol antes de guardar.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;

        if ($record->is(Auth::user()) && $record->hasRole('super_admin')) {
            $superAdminRole = \Spatie\Permission\Models\Role::query()
                ->where('name', 'super_admin')
                ->first();

            if ($superAdminRole) {
                $roles = array_map('intval', $data['roles'] ?? []);

                if (! in_array($superAdminRole->id, $roles, true)) {
                    $roles[] = $superAdminRole->id;
                }

                $data['roles'] = $roles;
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}