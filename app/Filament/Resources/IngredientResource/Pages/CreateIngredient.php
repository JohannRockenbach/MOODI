<?php

namespace App\Filament\Resources\IngredientResource\Pages;

use App\Filament\Resources\IngredientResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateIngredient extends CreateRecord
{
    protected static string $resource = IngredientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Asegurar que restaurant_id siempre sea 1 (restaurante único)
        $data['restaurant_id'] = 1;
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Crear el lote inicial automáticamente
        $ingredient = $this->getRecord();
        
        // Obtener los datos del lote inicial del formulario
        $initialQuantity = $this->data['initial_quantity'] ?? 0;
        $initialPurchaseDate = $this->data['initial_purchase_date'] ?? now();
        $initialExpirationDate = $this->data['initial_expiration_date'] ?? null;
        
        // Crear el primer lote solo si hay cantidad inicial
        if ($initialQuantity > 0) {
            $ingredient->batches()->create([
                'quantity' => $initialQuantity,
                'purchase_date' => $initialPurchaseDate,
                'expiration_date' => $initialExpirationDate,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}