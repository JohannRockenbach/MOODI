<?php

namespace App\Filament\Resources\CajaResource\Pages;

use App\Filament\Resources\CajaResource;
use App\Models\Caja;
use App\Models\Restaurant;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCajas extends ListRecords
{
    protected static string $resource = CajaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('crear')
                ->label('Abrir Caja') // Cambio de "Crear" a "Abrir"
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->modalHeading('Abrir Nueva Caja')
                ->modalDescription('Ingresa el saldo inicial para abrir la caja')
                ->modalSubmitActionLabel('Abrir Caja')
                ->modalCancelActionLabel('Cancelar')
                ->form([
                    // Campo oculto con restaurant_id = 1
                    Forms\Components\Hidden::make('restaurant_id')
                        ->default(1),
                    Forms\Components\TextInput::make('initial_balance')
                        ->label('Saldo Inicial')
                        ->prefix('$')
                        ->placeholder('0.00')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(99999999.99)
                        ->default(0),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->placeholder('Notas adicionales sobre la apertura de caja (opcional)')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    // Siempre usa restaurant_id = 1
                    $restaurantId = 1;
                    
                    // Validate no open caja exists
                    $existsOpen = Caja::where('restaurant_id', $restaurantId)
                        ->where('status', 'abierta')
                        ->exists();
                    
                    if ($existsOpen) {
                        Notification::make()
                            ->title('Error')
                            ->body('Ya existe una caja abierta. Ciérrela antes de abrir una nueva.')
                            ->danger()
                            ->send();
                        $this->halt();
                        return;
                    }
                    
                    // Validate no caja created today
                    $existsToday = Caja::where('restaurant_id', $restaurantId)
                        ->whereDate('opening_date', today())
                        ->exists();
                    
                    if ($existsToday) {
                        Notification::make()
                            ->title('Error')
                            ->body('Ya se creó una caja en la fecha de hoy.')
                            ->danger()
                            ->send();
                        $this->halt();
                        return;
                    }
                    
                    // Create caja
                    Caja::create([
                        'restaurant_id' => $restaurantId,
                        'opening_date' => now(),
                        'initial_balance' => $data['initial_balance'],
                        'notes' => $data['notes'] ?? null,
                        'status' => 'abierta',
                        'opening_user_id' => Auth::id(),
                    ]);
                    
                    Notification::make()
                        ->title('Caja abierta exitosamente')
                        ->success()
                        ->send();
                }),
        ];
    }
}
