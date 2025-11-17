<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Resources\Pages\EditRecord;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;
    
    // Redirigir a la lista después de editar
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
