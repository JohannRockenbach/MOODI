<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    protected static string $view = 'filament.resources.reservation-resource.pages.edit-reservation';

    /**
     * Edición operativa a ancho completo, con el lenguaje visual Stitch del
     * padre (GestionReservas — "Restaurante El Fogón").
     */
    protected ?string $maxContentWidth = MaxWidth::Full->value;
    
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
