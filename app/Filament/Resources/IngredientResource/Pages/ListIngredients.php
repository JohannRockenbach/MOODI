<?php

namespace App\Filament\Resources\IngredientResource\Pages;

use App\Filament\Resources\IngredientResource;
use App\Models\Ingredient;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListIngredients extends ListRecords
{
    protected static string $resource = IngredientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Acción: Actualizar Stock (agregar lote sin entrar a editar)
            Actions\Action::make('updateStock')
                ->label('Actualizar Stock')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('ingredient_id')
                        ->label('Ingrediente')
                        ->options(Ingredient::where('restaurant_id', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Selecciona el ingrediente al que agregarás stock'),
                    
                    Forms\Components\TextInput::make('quantity')
                        ->label('Cantidad')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->helperText('Cantidad del nuevo lote'),
                    
                    Forms\Components\DatePicker::make('purchase_date')
                        ->label('Fecha de Compra')
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                    
                    Forms\Components\DatePicker::make('expiration_date')
                        ->label('Fecha de Vencimiento')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->nullable()
                        ->helperText('Dejar vacío si el ingrediente no tiene vencimiento'),
                    
                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->maxLength(65535)
                        ->columnSpanFull()
                        ->placeholder('Observaciones sobre este lote (proveedor, calidad, etc.)'),
                ])
                ->action(function (array $data): void {
                    // Encontrar el ingrediente
                    $ingredient = Ingredient::find($data['ingredient_id']);
                    
                    // Crear el nuevo lote
                    $ingredient->batches()->create([
                        'quantity' => $data['quantity'],
                        'purchase_date' => $data['purchase_date'],
                        'expiration_date' => $data['expiration_date'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ]);
                    
                    // Notificación de éxito
                    Notification::make()
                        ->success()
                        ->title('Stock Actualizado')
                        ->body("Se agregó un lote de {$data['quantity']} al ingrediente {$ingredient->name}.")
                        ->send();
                }),
            
            // Acción: Crear Ingrediente (ya existente)
            Actions\CreateAction::make(),
        ];
    }
}
