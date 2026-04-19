<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        $trashedFilterValue = data_get(request()->query(), 'tableFilters.trashed.value');
        $isViewingDeleted = in_array($trashedFilterValue, [false, 0, '0'], true);

        return [
            Actions\Action::make('toggleDeletedClientes')
                ->label($isViewingDeleted ? 'Ver activos' : 'Ver eliminados')
                ->icon($isViewingDeleted ? 'heroicon-o-user-group' : 'heroicon-o-trash')
                ->url($this->getDeletedClientesToggleUrl($isViewingDeleted))
                ->color($isViewingDeleted ? 'gray' : 'warning')
                ->outlined(),
            Actions\CreateAction::make(),
        ];
    }

    protected function getDeletedClientesToggleUrl(bool $isViewingDeleted): string
    {
        if ($isViewingDeleted) {
            return static::getResource()::getUrl('index');
        }

        return static::getResource()::getUrl('index', [
            'tableFilters' => [
                'trashed' => [
                    'value' => '0',
                ],
            ],
        ]);
    }
}
