<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Table;
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
     * Prellenar el formulario con la mesa cuando se abre desde el Mapa de Mesas
     * (?table_id=N). Filament 3 no prellena parámetros de query por defecto en
     * CreateRecord; este es el mismo patrón que OrderResource\CreateOrder usa.
     * Solo se prellena si la mesa existe y pertenece al restaurante 1.
     */
    public function mount(?int $table_id = null): void
    {
        parent::mount();

        if ($table_id && Table::where('restaurant_id', 1)->whereKey($table_id)->exists()) {
            $this->form->fill(['table_id' => $table_id]);
        }
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
