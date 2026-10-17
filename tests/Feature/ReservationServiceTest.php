<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Table;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReservationService
    {
        return app(ReservationService::class);
    }

    private function baseData(Table $table, array $overrides = []): array
    {
        return array_merge([
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
            'reservation_time' => now()->addHours(3),
            'guest_count' => 2,
            'status' => 'pending',
            'customer_id' => \App\Models\User::factory()->create()->id,
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    // HAPPY PATH
    // ─────────────────────────────────────────────────────────────

    public function test_create_reserva_en_mesa_disponible(): void
    {
        $table = Table::factory()->available()->create();

        $reservation = $this->service()->create($this->baseData($table));

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
        // El observer marca la mesa como reservada.
        $this->assertSame(Table::STATUS_RESERVED, $table->fresh()->status);
    }

    public function test_update_reserva_excluye_su_propio_registro_del_solapamiento(): void
    {
        $table = Table::factory()->create(['status' => Table::STATUS_AVAILABLE, 'capacity' => 6]);

        $reservation = $this->service()->create($this->baseData($table));

        // Actualizar la MISMA reserva a la misma hora: no debe auto-conflictarse.
        $this->service()->update($reservation, $this->baseData($table, [
            'guest_count' => 4,
        ]));

        $this->assertSame(4, $reservation->fresh()->guest_count);
    }

    // ─────────────────────────────────────────────────────────────
    // SEGURIDAD / DISPONIBILIDAD DE LA MESA
    // ─────────────────────────────────────────────────────────────

    public function test_create_deniega_reserva_en_mesa_ocupada(): void
    {
        $table = Table::factory()->occupied()->create();

        $this->expectException(ValidationException::class);
        $this->service()->create($this->baseData($table));
    }

    public function test_create_deniega_reserva_en_mesa_en_mantenimiento(): void
    {
        $table = Table::factory()->create(['status' => Table::STATUS_MAINTENANCE]);

        $this->expectException(ValidationException::class);
        $this->service()->create($this->baseData($table));
    }

    public function test_update_deniega_mover_reserva_a_mesa_ocupada(): void
    {
        $tableLibre = Table::factory()->available()->create();
        $tableOcupada = Table::factory()->occupied()->create();

        $reservation = $this->service()->create($this->baseData($tableLibre));

        $this->expectException(ValidationException::class);
        $this->service()->update($reservation, $this->baseData($tableOcupada));
    }

    // ─────────────────────────────────────────────────────────────
    // EDGE CASES
    // ─────────────────────────────────────────────────────────────

    public function test_create_deniega_solapamiento_con_reserva_activa_en_misma_mesa(): void
    {
        $table = Table::factory()->available()->create();
        $this->service()->create($this->baseData($table, [
            'reservation_time' => now()->addHours(3),
        ]));

        // Misma mesa + hora dentro de la ventana ±2h -> conflicto.
        $this->expectException(ValidationException::class);
        $this->service()->create($this->baseData($table, [
            'reservation_time' => now()->addHours(4),
        ]));
    }

    public function test_create_permite_reserva_fuera_de_ventana_de_solapamiento(): void
    {
        $table = Table::factory()->available()->create();
        $this->service()->create($this->baseData($table, [
            'reservation_time' => now()->addHours(3),
        ]));

        // 6 horas después: fuera de la ventana ±2h -> permitido.
        $second = $this->service()->create($this->baseData($table, [
            'reservation_time' => now()->addHours(9),
        ]));

        $this->assertNotNull($second);
        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_create_no_considera_solapamiento_con_reserva_cancelada(): void
    {
        $table = Table::factory()->available()->create();
        $this->service()->create($this->baseData($table, [
            'status' => 'cancelled',
            'reservation_time' => now()->addHours(3),
        ]));

        // Reserva cancelada NO bloquea: se puede crear otra en el mismo slot.
        $second = $this->service()->create($this->baseData($table, [
            'reservation_time' => now()->addHours(3),
        ]));

        $this->assertNotNull($second);
    }

    public function test_create_deniega_comensales_que_exceden_capacidad(): void
    {
        $table = Table::factory()->create(['status' => Table::STATUS_AVAILABLE, 'capacity' => 4]);

        $this->expectException(ValidationException::class);
        $this->service()->create($this->baseData($table, [
            'guest_count' => 6,
        ]));
    }

    public function test_create_acepta_comensales_igual_a_capacidad(): void
    {
        $table = Table::factory()->create(['status' => Table::STATUS_AVAILABLE, 'capacity' => 4]);

        $reservation = $this->service()->create($this->baseData($table, [
            'guest_count' => 4,
        ]));

        $this->assertNotNull($reservation);
    }
}