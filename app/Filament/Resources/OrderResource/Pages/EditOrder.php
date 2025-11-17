<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;
    
    //  Redirigir a la lista después de editar
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Validar antes de guardar que no se retroceda de estados completados
     */
    protected function beforeSave(): void
    {
        $order = $this->getRecord();
        $originalStatus = $order->getOriginal('status');
        $newStatus = $this->data['status'];

        // Validar que no se pueda retroceder de completed
        if ($originalStatus === 'completed' && in_array($newStatus, ['pending', 'processing'])) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Acción no permitida')
                ->body('No se puede cambiar el estado de un pedido completado.')
                ->persistent()
                ->send();
            
            // Detener el guardado lanzando una excepción
            $this->halt();
        }
    }
}
