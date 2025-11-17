<?php

namespace App\Filament\Resources\CajaResource\Pages;

use App\Filament\Resources\CajaResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditCaja extends EditRecord
{
    protected static string $resource = CajaResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Prevenir edición de cajas cerradas
        if ($this->record->status === 'cerrada') {
            Notification::make()
                ->title('Caja Cerrada')
                ->body('No se puede editar una caja que ya ha sido cerrada.')
                ->warning()
                ->send();
            
            $this->redirect(static::getResource()::getUrl('index'));
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Prevenir cambio de estado a cerrada desde el formulario
        if (isset($data['status']) && $data['status'] === 'cerrada' && $this->record->status === 'abierta') {
            Notification::make()
                ->title('Error')
                ->body('Las cajas deben cerrarse usando el botón "Cerrar Caja" en la tabla.')
                ->danger()
                ->send();
            
            $data['status'] = 'abierta';
        }
        
        // If the user clears the initial_balance input (or the masked input produces
        // an empty value), preserve the original value from the record to avoid
        // accidentally saving 0.
        $record = $this->record;

        if (array_key_exists('initial_balance', $data) && ($data['initial_balance'] === null || $data['initial_balance'] === '')) {
            $data['initial_balance'] = $record->initial_balance;
        }

        if (array_key_exists('final_balance', $data) && ($data['final_balance'] === null || $data['final_balance'] === '')) {
            $data['final_balance'] = $record->final_balance;
        }

        return $data;
    }
}
