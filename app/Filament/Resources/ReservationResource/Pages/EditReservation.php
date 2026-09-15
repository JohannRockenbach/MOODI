<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;
    
    // Redirigir a la lista después de editar
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Delegar la actualización a ReservationService: validación atómica con
     * row-lock, excluyendo este mismo registro del solapamiento.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ReservationService::class)->update($record, $data);
    }
}
