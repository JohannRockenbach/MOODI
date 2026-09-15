<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableStateMachineTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────
    // OCCUPY
    // ─────────────────────────────────────────────────────────────

    public function test_occupy_avanza_desde_available_a_occupied(): void
    {
        $table = Table::factory()->available()->create();

        $result = $table->occupy();

        $this->assertTrue($result);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_occupy_avanza_desde_reserved_a_occupied(): void
    {
        $table = Table::factory()->reserved()->create();

        $result = $table->occupy();

        $this->assertTrue($result);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_occupy_no_pisa_mesa_ocupada(): void
    {
        $table = Table::factory()->occupied()->create();

        $result = $table->occupy();

        $this->assertFalse($result);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_occupy_no_pisa_mesa_en_mantenimiento(): void
    {
        $table = Table::factory()->create(['status' => Table::STATUS_MAINTENANCE]);

        $result = $table->occupy();

        $this->assertFalse($result);
        $this->assertSame(Table::STATUS_MAINTENANCE, $table->fresh()->status);
    }

    // ─────────────────────────────────────────────────────────────
    // RELEASE
    // ─────────────────────────────────────────────────────────────

    public function test_release_libera_mesa_sin_pedidos_ni_reservas(): void
    {
        $table = Table::factory()->occupied()->create();

        $result = $table->release();

        $this->assertTrue($result);
        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_release_no_libera_mesa_con_pedidos_activos(): void
    {
        $table = Table::factory()->occupied()->create();
        Order::factory()->create([
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
            'status' => 'pending',
            'type' => 'salon',
        ]);

        $result = $table->release();

        $this->assertFalse($result);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_release_no_libera_mesa_que_no_esta_ocupada(): void
    {
        $table = Table::factory()->available()->create();

        $result = $table->release();

        $this->assertFalse($result);
        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_release_con_reservas_futuras_deja_la_mesa_reservada(): void
    {
        $table = Table::factory()->occupied()->create();

        Reservation::factory()->create([
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
            'status' => 'confirmed',
            'reservation_time' => now()->addHour(),
        ]);

        $result = $table->release();

        $this->assertTrue($result);
        $this->assertSame(Table::STATUS_RESERVED, $table->fresh()->status);
    }

    public function test_release_con_reservas_pasadas_vuelve_a_available(): void
    {
        $table = Table::factory()->occupied()->create();

        Reservation::factory()->create([
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
            'status' => 'confirmed',
            'reservation_time' => now()->subHour(),
        ]);

        $result = $table->release();

        $this->assertTrue($result);
        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }

    // ─────────────────────────────────────────────────────────────
    // INTEGRACIÓN CON OBSERVERS (pedido completa/cancela la mesa)
    // ─────────────────────────────────────────────────────────────

    public function test_completar_ultimo_pedido_libera_la_mesa(): void
    {
        $table = Table::factory()->available()->create();
        $table->occupy();

        $order = Order::factory()->create([
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
            'status' => 'pending',
            'type' => 'salon',
        ]);

        $order->update(['status' => 'completed']);

        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_cancelar_ultimo_pedido_libera_la_mesa(): void
    {
        $table = Table::factory()->available()->create();
        $table->occupy();

        $order = Order::factory()->create([
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
            'status' => 'pending',
            'type' => 'salon',
        ]);

        $order->update(['status' => 'cancelled']);

        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_completar_un_pedido_no_libera_mesa_con_otro_pedido_activo(): void
    {
        $table = Table::factory()->available()->create();
        $table->occupy();

        Order::factory()->create([
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
            'status' => 'pending',
            'type' => 'salon',
        ]);
        $order2 = Order::factory()->create([
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
            'status' => 'processing',
            'type' => 'salon',
        ]);

        $order2->update(['status' => 'completed']);

        // Sigue habiendo un pedido 'pending', la mesa debe seguir ocupada.
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_completar_pedido_libera_a_reserved_si_hay_reserva_futura(): void
    {
        $table = Table::factory()->available()->create();
        $table->occupy();

        Reservation::factory()->create([
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
            'status' => 'confirmed',
            'reservation_time' => now()->addHour(),
        ]);

        $order = Order::factory()->create([
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
            'status' => 'pending',
            'type' => 'salon',
        ]);

        $order->update(['status' => 'completed']);

        $this->assertSame(Table::STATUS_RESERVED, $table->fresh()->status);
    }
}