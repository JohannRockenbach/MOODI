<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Facades\Log;

class ReservationObserver
{
    /**
     * Estados de reserva que mantienen la mesa reservada.
     */
    private const ACTIVE_STATUSES = ['pending', 'confirmed'];

    /**
     * Handle the Reservation "created" event.
     * Cuando se crea una reserva activa, marcar la mesa como 'reserved'
     * (solo si la mesa está disponible; nunca pisa una mesa ocupada o en mantenimiento).
     */
    public function created(Reservation $reservation): void
    {
        if ($this->isActive($reservation)) {
            $this->reserveTableSafely($reservation->table_id, $reservation->id);
            Log::info("Mesa #{$reservation->table_id} marcada como RESERVADA (Reserva #{$reservation->id} creada)");
        }
    }

    /**
     * Handle the Reservation "updated" event.
     * - Si cambió la mesa, reevaluar AMBAS mesas de forma segura.
     * - Si cambió el estado, reservar o liberar la mesa según corresponda.
     */
    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged('table_id')) {
            // La mesa anterior se libera solo si no tiene otras reservas activas
            // y no está ocupada/en mantenimiento.
            $this->releaseTableSafely($reservation->getOriginal('table_id'), $reservation->id);

            // La mesa nueva se reserva solo si la reserva sigue activa.
            if ($this->isActive($reservation)) {
                $this->reserveTableSafely($reservation->table_id, $reservation->id);
            }

            return;
        }

        if ($reservation->wasChanged('status')) {
            // Si la reserva se cancela, liberar la mesa de forma segura.
            if ($reservation->status === 'cancelled') {
                $this->releaseTableSafely($reservation->table_id, $reservation->id);
                Log::info("Mesa #{$reservation->table_id} liberada (Reserva #{$reservation->id} cancelada)");
            } elseif ($this->isActive($reservation)) {
                // Si la reserva pasa a pending/confirmed, asegurar la mesa.
                $this->reserveTableSafely($reservation->table_id, $reservation->id);
                Log::info("Mesa #{$reservation->table_id} reservada (Reserva #{$reservation->id} activa)");
            }
        }
    }

    /**
     * Handle the Reservation "deleted" event.
     * Liberar la mesa solo si no hay otras reservas activas y no está ocupada.
     */
    public function deleted(Reservation $reservation): void
    {
        $this->releaseTableSafely($reservation->table_id, $reservation->id);
        Log::info("Mesa #{$reservation->table_id} liberada (Reserva #{$reservation->id} eliminada)");
    }

    /**
     * ¿La reserva mantiene la mesa reservada (pending/confirmed)?
     */
    private function isActive(Reservation $reservation): bool
    {
        return in_array($reservation->status, self::ACTIVE_STATUSES, true);
    }

    /**
     * ¿Hay otras reservas activas (pending/confirmed) sobre la mesa,
     * excluyendo la reserva que está generando el evento?
     */
    private function hasOtherActiveReservations(int $tableId, ?int $exceptReservationId = null): bool
    {
        return Reservation::query()
            ->where('table_id', $tableId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->when($exceptReservationId !== null, fn ($query) => $query->where('id', '!=', $exceptReservationId))
            ->exists();
    }

    /**
     * ¿Se puede tocar la mesa? Solo cuando NO está ocupada ni en mantenimiento.
     */
    private function shouldOccupyTable(Table $table): bool
    {
        return ! in_array($table->status, [
            Table::STATUS_OCCUPIED,
            Table::STATUS_MAINTENANCE,
        ], true);
    }

    /**
     * Marcar la mesa como 'reserved' SOLO si está disponible
     * (available -> reserved). Nunca cambia mesas ocupadas/en mantenimiento
     * ni pisa un 'reserved' de otra reserva.
     */
    private function reserveTableSafely(?int $tableId, ?int $exceptReservationId = null): void
    {
        $table = $tableId ? Table::find($tableId) : null;
        if (! $table || ! $this->shouldOccupyTable($table)) {
            return;
        }

        if ($table->status === Table::STATUS_AVAILABLE) {
            $table->fill(['status' => Table::STATUS_RESERVED])->saveQuietly();
        }
    }

    /**
     * Liberar la mesa (reserved -> available) solo si está reservada,
     * no tiene otras reservas activas y no está ocupada/en mantenimiento.
     */
    private function releaseTableSafely(?int $tableId, ?int $exceptReservationId = null): void
    {
        $table = $tableId ? Table::find($tableId) : null;
        if (! $table || ! $this->shouldOccupyTable($table)) {
            return;
        }

        if ($table->status !== Table::STATUS_RESERVED) {
            return;
        }

        if ($this->hasOtherActiveReservations($table->id, $exceptReservationId)) {
            return;
        }

        $table->fill(['status' => Table::STATUS_AVAILABLE])->saveQuietly();
    }
}