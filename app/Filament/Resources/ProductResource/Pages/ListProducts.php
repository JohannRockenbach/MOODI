<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    // Refrescar automáticamente cada 2 segundos
    protected $listeners = ['$refresh'];
    
    // Propiedad para habilitar polling
    protected ?string $pollingInterval = '2s';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
