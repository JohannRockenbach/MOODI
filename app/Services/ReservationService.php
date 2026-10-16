<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Collection;
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
     * Listar las mesas disponibles para reservar en un horario dado.
     *
     * Reutiliza la MISMA regla de solapamiento que `create()` (ventana ±2h
     * sobre reservas pending/confirmed) para que el formulario web muestre
     * solo mesas realmente reservables: status = available, capacidad
     * suficiente y sin reserva activa superpuesta. Ordenadas por capacidad
     * ascendente y luego por número.
     */
    public function availableTables(\Carbon\Carbon $time, int $guestCount, int $restaurantId = 1): Collection
    {
        $conflictingTableIds = $this->overlapQuery($time)->pluck('table_id');

        return Table::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', Table::STATUS_AVAILABLE)
            ->where('capacity', '>=', max(1, $guestCount))
            ->whereNotIn('id', $conflictingTableIds)
            ->orderBy('capacity')
            ->orderBy('number')
            ->get();
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

        $conflict = $this->overlapQuery($reservationTime)
            ->where('table_id', $tableId)
            ->when($exceptReservationId !== null, fn ($query) => $query->where('id', '!=', $exceptReservationId))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'reservation_time' => 'Esta mesa ya tiene una reserva activa en ese horario (±' . self::CONFLICT_WINDOW_HOURS . ' horas). Elige otro horario o mesa.',
            ]);
        }
    }

    /**
     * Query base de reservas activas (pending/confirmed) cuya fecha cae dentro
     * de la ventana ±N horas alrededor de $time. Fuente única de la regla de
     * solapamiento: la usan tanto `assertNoOverlap` (create/update) como
     * `availableTables` (listado del formulario web).
     */
    private function overlapQuery(\Carbon\Carbon $time): \Illuminate\Database\Eloquent\Builder
    {
        return Reservation::query()
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('reservation_time', [
                $time->copy()->subHours(self::CONFLICT_WINDOW_HOURS),
                $time->copy()->addHours(self::CONFLICT_WINDOW_HOURS),
            ]);
    }
}