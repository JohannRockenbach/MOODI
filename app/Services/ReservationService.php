<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio central de reservas.
 *
 * Mueve la regla de negocio "una mesa no puede tener dos reservas activas
 * superpuestas" desde la capa de formulario (closure de Filament) a una capa
 * atómica con row-lock: dos usuarios creando reservas simultáneas para la
 * misma mesa NO pueden colisionar (C4).
 *
 * Además valida que la mesa no esté ocupada/en mantenimiento y que la
 * capacidad alcance para los comensales (C5).
 */
class ReservationService
{
    /**
     * Ventana de solapamiento de una reserva (horas alrededor de
     * reservation_time). La app no modela duración; esta ventana es la
     * duración estándar de negocio.
     */
    public const CONFLICT_WINDOW_HOURS = 2;

    /**
     * Crear una reserva de forma atómica.
     *
     * @throws ValidationException si la mesa no está disponible o hay un
     *         solapamiento con otra reserva activa.
     */
    public function create(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            $table = $this->lockTable((int) ($data['table_id'] ?? 0));

            $this->assertTableAvailable($table);
            $this->assertCapacity($table, (int) ($data['guest_count'] ?? 0));
            $this->assertNoOverlap($table->id, $data['reservation_time'], null);

            return Reservation::create($data);
        });
    }

    /**
     * Actualizar una reserva de forma atómica.
     *
     * @throws ValidationException si la mesa no está disponible o hay un
     *         solapamiento con otra reserva activa (excluyendo este registro).
     */
    public function update(Reservation $reservation, array $data): Reservation
    {
        return DB::transaction(function () use ($reservation, $data) {
            $tableId = (int) ($data['table_id'] ?? $reservation->table_id);
            $table = $this->lockTable($tableId);

            $this->assertTableAvailable($table);
            $this->assertCapacity($table, (int) ($data['guest_count'] ?? $reservation->guest_count));
            $this->assertNoOverlap($table->id, $data['reservation_time'] ?? $reservation->reservation_time, $reservation->id);

            $reservation->fill($data)->save();

            return $reservation;
        });
    }

    /**
     * Bloquear la fila de la mesa para serializar reservas concurrentes
     * sobre la misma mesa.
     */
    private function lockTable(int $tableId): Table
    {
        $table = Table::query()->lockForUpdate()->find($tableId);

        if (! $table) {
            throw ValidationException::withMessages([
                'table_id' => 'La mesa seleccionada no existe.',
            ]);
        }

        return $table;
    }

    /**
     * No se puede reservar una mesa ocupada ni en mantenimiento.
     */
    private function assertTableAvailable(Table $table): void
    {
        if (in_array($table->status, [
            Table::STATUS_OCCUPIED,
            Table::STATUS_MAINTENANCE,
        ], true)) {
            throw ValidationException::withMessages([
                'table_id' => 'La mesa está ' . ($table->status === Table::STATUS_OCCUPIED ? 'ocupada' : 'en mantenimiento') . '. No se puede reservar.',
            ]);
        }
    }

    /**
     * La cantidad de comensales no puede exceder la capacidad de la mesa.
     */
    private function assertCapacity(Table $table, int $guestCount): void
    {
        if ($guestCount < 1) {
            throw ValidationException::withMessages([
                'guest_count' => 'La cantidad de comensales debe ser al menos 1.',
            ]);
        }

        if ($guestCount > $table->capacity) {
            throw ValidationException::withMessages([
                'guest_count' => "La mesa tiene capacidad para {$table->capacity} personas y la reserva es para {$guestCount}.",
            ]);
        }
    }

    /**
     * No puede existir otra reserva activa (pending/confirmed) sobre la misma
     * mesa cuya reservation_time caiga dentro de la ventana ±N horas.
     */
    private function assertNoOverlap(int $tableId, mixed $reservationTime, ?int $exceptReservationId): void
    {
        $reservationTime = \Carbon\Carbon::parse($reservationTime);

        $conflict = Reservation::query()
            ->where('table_id', $tableId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('reservation_time', [
                $reservationTime->copy()->subHours(self::CONFLICT_WINDOW_HOURS),
                $reservationTime->copy()->addHours(self::CONFLICT_WINDOW_HOURS),
            ])
            ->when($exceptReservationId !== null, fn ($query) => $query->where('id', '!=', $exceptReservationId))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'reservation_time' => 'Esta mesa ya tiene una reserva activa en ese horario (±' . self::CONFLICT_WINDOW_HOURS . ' horas). Elige otro horario o mesa.',
            ]);
        }
    }
}