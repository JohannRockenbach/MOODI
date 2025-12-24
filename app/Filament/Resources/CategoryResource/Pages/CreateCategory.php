<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getRedirectUrl(): string
    {
        // After creating a category, redirect back to the index instead of the edit page.
        return $this->getResource()::getUrl('index');
    }
    
    /**
     * Después de crear la categoría, asignar el category_id a los productos
     */
    protected function afterCreate(): void
    {
        $category = $this->record;
        
        // Si hay productos seleccionados, asegurar que tienen el category_id
        if ($category->products()->exists()) {
            $category->products()->update(['category_id' => $category->id]);
        }
    }
}
