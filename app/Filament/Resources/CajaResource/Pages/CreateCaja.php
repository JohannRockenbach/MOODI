<?php

namespace App\Filament\Resources\CajaResource\Pages;

use App\Filament\Resources\CajaResource;
use App\Models\Caja;
use App\Models\Restaurant;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreateCaja extends CreateRecord
{
    protected static string $resource = CajaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Asegurar que siempre se use restaurant_id = 1
        $data['restaurant_id'] = 1;
        $data['opening_user_id'] = Auth::id();
        $data['status'] = 'abierta';
        $data['opening_date'] = $data['opening_date'] ?? now();
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('abrir_caja_rapida')
                ->label('Abrir Caja Rápida')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Abrir Nueva Caja')
                ->modalDescription('Ingresa el monto inicial para abrir la caja')
                ->form([
                    // Campo oculto con restaurant_id = 1
                    Forms\Components\Hidden::make('restaurant_id')
                        ->default(1),
                    Forms\Components\TextInput::make('initial_balance')
                        ->label('Saldo Inicial')
                        ->prefix('$')
                        ->placeholder('0,00')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(99999999.99)
                        ->default(0),
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
                        return;
                    }
                    
                    // Create caja
                    Caja::create([
                        'restaurant_id' => $restaurantId,
                        'opening_date' => now(),
                        'initial_balance' => $data['initial_balance'],
                        'status' => 'abierta',
                        'opening_user_id' => Auth::id(),
                    ]);
                    
                    Notification::make()
                        ->title('Caja abierta exitosamente')
                        ->success()
                        ->send();
                    
                    return redirect()->to(static::getResource()::getUrl('index'));
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Abrir Caja') // Cambio de "Crear" a "Abrir"
                ->color('success')
                ->icon('heroicon-o-lock-open')
                ->requiresConfirmation()
                ->modalHeading('Confirmar Apertura de Caja')
                ->modalDescription('¿Estás seguro que deseas abrir la caja con el saldo inicial ingresado?')
                ->modalSubmitActionLabel('Sí, Abrir Caja')
                ->modalCancelActionLabel('Cancelar'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
