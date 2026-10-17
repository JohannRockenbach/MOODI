<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->status === 'annulled' || ! is_null($this->record->annulled_at)) {
            Notification::make()
                ->title('Venta anulada')
                ->body('No se puede editar una venta anulada.')
                ->warning()
                ->send();

            $this->redirect(static::getResource()::getUrl('index'));
        }
    }

    // Redirigir a la lista después de editar
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeSave(): void
    {
        if ($this->record->status === 'annulled' || ! is_null($this->record->annulled_at)) {
            Notification::make()
                ->title('Venta anulada')
                ->body('No se puede editar una venta anulada.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
