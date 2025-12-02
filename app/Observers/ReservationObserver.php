<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Facades\Log;

class ReservationObserver
{
    /**
     * Handle the Reservation "created" event.
     * Cuando se crea una reserva, marcar la mesa como 'reserved'
     */
    public function created(Reservation $reservation): void
    {
        if (in_array($reservation->status, ['pending', 'confirmed'])) {
            $this->updateTableStatus($reservation->table_id, 'reserved');
            Log::info("Mesa #{$reservation->table_id} marcada como RESERVADA (Reserva #{$reservation->id} creada)");
        }
    }

    /**
     * Handle the Reservation "updated" event.
     * Actualizar el estado de la mesa según el estado de la reserva
     */
    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged('status')) {
            // Si la reserva se cancela, liberar la mesa
            if ($reservation->status === 'cancelled') {
                // Solo liberar si no hay otras reservas activas
                $activeReservations = Reservation::where('table_id', $reservation->table_id)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where('id', '!=', $reservation->id)
                    ->exists();

                if (!$activeReservations) {
                    $this->updateTableStatus($reservation->table_id, 'available');
                    Log::info("Mesa #{$reservation->table_id} liberada (Reserva #{$reservation->id} cancelada)");
                }
            }
            
            // Si se confirma una reserva pendiente, asegurar que la mesa esté reservada
            if ($reservation->status === 'confirmed' && $reservation->getOriginal('status') === 'pending') {
                $this->updateTableStatus($reservation->table_id, 'reserved');
                Log::info("Mesa #{$reservation->table_id} confirmada como RESERVADA (Reserva #{$reservation->id})");
            }
        }
    }

    /**
     * Handle the Reservation "deleted" event.
     * Liberar la mesa si se elimina la reserva
     */
    public function deleted(Reservation $reservation): void
    {
        // Solo liberar si no hay otras reservas activas
        $activeReservations = Reservation::where('table_id', $reservation->table_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if (!$activeReservations) {
            $this->updateTableStatus($reservation->table_id, 'available');
            Log::info("Mesa #{$reservation->table_id} liberada (Reserva #{$reservation->id} eliminada)");
        }
    }

    /**
     * Actualizar el estado de una mesa
     */
    private function updateTableStatus(int $tableId, string $status): void
    {
        $table = Table::find($tableId);
        if ($table) {
            $table->update(['status' => $status]);
        }
    }
}
