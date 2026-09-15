<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;
    
    // Redirigir a la lista después de crear
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Delegar la creación a ReservationService: validación atómica con
     * row-lock (anti doble-reserva real, no solo UX del formulario).
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ReservationService::class)->create($data);
    }
}
