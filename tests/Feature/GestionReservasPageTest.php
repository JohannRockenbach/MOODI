<?php

namespace Tests\Feature;

use App\Filament\Pages\GestionReservas;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * GestionReservas — Testing Strategy B del proyecto:
 * 1) happy path de acceso/render (Mozo ve reservas con cliente/mesa/fecha),
 * 2) seguridad/autorización (Cocinero y Cajero denegados, 403),
 * 3) edge cases: confirmar/cancelar disparan el ReservationObserver
 *    (reserva/libera la mesa automáticamente, NO se hace manual).
 */
beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function makeReservasUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

// ─────────────────────────────────────────────────────────────
// HAPPY PATH
// ─────────────────────────────────────────────────────────────

it('renderiza el listado de reservas con cliente, mesa y fecha (Mozo)', function () {
    $mozo = makeReservasUser('Mozo');
    $cliente = User::factory()->create(['name' => 'Familia Gómez']);

    $mesa = Table::factory()->create([
        'number' => 3,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    $fecha = now()->addHours(3)->startOfHour();
    $reserva = Reservation::factory()->create([
        'table_id' => $mesa->id,
        'restaurant_id' => 1,
        'customer_id' => $cliente->id,
        'status' => 'pending',
        'guest_count' => 4,
        'reservation_time' => $fecha,
    ]);

    Livewire::actingAs($mozo)
        ->test(GestionReservas::class)
        ->assertOk()
        ->assertSee('Familia Gómez')
        ->assertSee('Mesa #3')
        ->assertSee($fecha->format('d/m/Y H:i'))
        ->assertSee('PENDIENTES')
        ->assertSee('4 personas');

    $reservations = Livewire::actingAs($mozo)->test(GestionReservas::class)->get('reservations');

    expect($reservations)->toHaveCount(1)
        ->and($reservations[0]['id'])->toBe($reserva->id)
        ->and($reservations[0]['customer_name'])->toBe('Familia Gómez');
});

// ─────────────────────────────────────────────────────────────
// SEGURIDAD / AUTORIZACIÓN
// ─────────────────────────────────────────────────────────────

it('permite acceder solo a super_admin y Mozo', function () {
    foreach (['super_admin', 'Mozo'] as $role) {
        $user = makeReservasUser($role);
        auth()->login($user);

        expect(GestionReservas::canAccess())->toBeTrue("{$role} debería poder acceder");
    }
});

it('deniega acceso a Cocinero y Cajero (canAccess false y 403 al montar)', function () {
    foreach (['Cocinero', 'Cajero'] as $role) {
        $user = makeReservasUser($role);

        expect(GestionReservas::canAccess())->toBeFalse("{$role} no debería acceder");

        Livewire::actingAs($user)
            ->test(GestionReservas::class)
            ->assertStatus(403);
    }
});

// ─────────────────────────────────────────────────────────────
// EDGE CASES
// ─────────────────────────────────────────────────────────────

it('confirmar una reserva dispara el ReservationObserver (mesa queda reservada)', function () {
    $mozo = makeReservasUser('Mozo');
    $cliente = User::factory()->create(['name' => 'Ana']);

    $mesa = Table::factory()->create([
        'number' => 7,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    // Al CREAR la reserva el observer ya marca la mesa reserved.
    $reserva = Reservation::factory()->create([
        'table_id' => $mesa->id,
        'restaurant_id' => 1,
        'customer_id' => $cliente->id,
        'status' => 'pending',
        'reservation_time' => now()->addDay(),
    ]);

    expect($mesa->fresh()->status)->toBe(Table::STATUS_RESERVED);

    Livewire::actingAs($mozo)
        ->test(GestionReservas::class)
        ->call('confirmReservation', $reserva->id)
        ->assertNotified('Reserva Confirmada');

    expect($reserva->fresh()->status)->toBe('confirmed')
        ->and($mesa->fresh()->status)->toBe(Table::STATUS_RESERVED);
});

it('cancelar una reserva confirmada libera la mesa automáticamente', function () {
    $mozo = makeReservasUser('Mozo');
    $cliente = User::factory()->create(['name' => 'Carlos']);

    $mesa = Table::factory()->create([
        'number' => 8,
        'status' => Table::STATUS_RESERVED,
        'restaurant_id' => 1,
    ]);

    $reserva = Reservation::factory()->create([
        'table_id' => $mesa->id,
        'restaurant_id' => 1,
        'customer_id' => $cliente->id,
        'status' => 'confirmed',
        'reservation_time' => now()->addDay(),
    ]);

    Livewire::actingAs($mozo)
        ->test(GestionReservas::class)
        ->call('cancelReservation', $reserva->id)
        ->assertNotified('Reserva Cancelada');

    expect($reserva->fresh()->status)->toBe('cancelled')
        // Sin otras reservas activas: la mesa vuelve a available.
        ->and($mesa->fresh()->status)->toBe(Table::STATUS_AVAILABLE);
});

it('no cancela una reserva ya cancelada (guardas de estado)', function () {
    $mozo = makeReservasUser('Mozo');
    $cliente = User::factory()->create(['name' => 'Diana']);

    $mesa = Table::factory()->create([
        'number' => 9,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    $reserva = Reservation::factory()->create([
        'table_id' => $mesa->id,
        'restaurant_id' => 1,
        'customer_id' => $cliente->id,
        'status' => 'cancelled',
        'reservation_time' => now()->addDay(),
    ]);

    Livewire::actingAs($mozo)
        ->test(GestionReservas::class)
        ->call('cancelReservation', $reserva->id)
        ->assertNotNotified();

    expect($reserva->fresh()->status)->toBe('cancelled');
});

it('filtra por fecha: Hoy muestra las reservas de hoy y Próximas las futuras', function () {
    $mozo = makeReservasUser('Mozo');
    $clienteHoy = User::factory()->create(['name' => 'Hoy Cliente']);
    $clienteFuturo = User::factory()->create(['name' => 'Futuro Cliente']);

    $mesaHoy = Table::factory()->create(['number' => 10, 'status' => Table::STATUS_RESERVED, 'restaurant_id' => 1]);
    $mesaFutura = Table::factory()->create(['number' => 11, 'status' => Table::STATUS_RESERVED, 'restaurant_id' => 1]);

    Reservation::factory()->create([
        'table_id' => $mesaHoy->id,
        'restaurant_id' => 1,
        'customer_id' => $clienteHoy->id,
        'status' => 'confirmed',
        'reservation_time' => now()->addHour(),
    ]);
    Reservation::factory()->create([
        'table_id' => $mesaFutura->id,
        'restaurant_id' => 1,
        'customer_id' => $clienteFuturo->id,
        'status' => 'confirmed',
        'reservation_time' => now()->addDays(3),
    ]);

    $component = Livewire::actingAs($mozo)->test(GestionReservas::class);

    // Hoy: solo la de hoy.
    $component->set('activeDate', 'today')
        ->assertSee('Hoy Cliente')
        ->assertDontSee('Futuro Cliente');

    // Próximas: la de hoy y la futura (ambas > now).
    $component->set('activeDate', 'upcoming')
        ->assertSee('Hoy Cliente')
        ->assertSee('Futuro Cliente');

    // Todas: ambas.
    $component->set('activeDate', 'all');
    expect(collect($component->instance()->visibleReservations)->pluck('customer_name'))
        ->toContain('Hoy Cliente')
        ->toContain('Futuro Cliente');
});
// ─────────────────────────────────────────────────────────────
// CREACIÓN (modal inline, ReservationService)
// ─────────────────────────────────────────────────────────────

it('crea una reserva desde el modal con validación atómica (ReservationService)', function () {
    $mozo = makeReservasUser('Mozo');
    $cliente = User::factory()->create(['name' => 'Nuevo Cliente']);

    $mesa = Table::factory()->create([
        'number' => 5,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    Livewire::actingAs($mozo)
        ->test(GestionReservas::class)
        ->set('modalNuevaReserva', true)
        ->set('nuevaCustomerId', $cliente->id)
        ->set('nuevaTableId', $mesa->id)
        ->set('nuevaFecha', now()->addHours(3)->format('Y-m-d H:i:00'))
        ->set('nuevaPersonas', 4)
        ->call('crearReserva')
        ->assertNotified()
        ->assertSet('modalNuevaReserva', false);

    expect(Reservation::count())->toBe(1)
        ->and(Reservation::first()->customer_id)->toBe($cliente->id)
        ->and(Reservation::first()->table_id)->toBe($mesa->id)
        ->and(Reservation::first()->status)->toBe('pending');
});

it('rechaza crear reserva en una mesa ocupada (regla de negocio)', function () {
    $mozo = makeReservasUser('Mozo');
    $cliente = User::factory()->create(['name' => 'Cli Ocupada']);

    // La mesa está OCUPADA con pedidos: no admite reservas.
    $mesa = Table::factory()->create([
        'number' => 8,
        'status' => Table::STATUS_OCCUPIED,
        'restaurant_id' => 1,
    ]);

    Livewire::actingAs($mozo)
        ->test(GestionReservas::class)
        ->set('modalNuevaReserva', true)
        ->set('nuevaCustomerId', $cliente->id)
        ->set('nuevaTableId', $mesa->id)
        ->set('nuevaFecha', now()->addHours(3)->format('Y-m-d H:i:00'))
        ->set('nuevaPersonas', 2)
        ->call('crearReserva')
        ->assertNotified('No se pudo crear la reserva');

    expect(Reservation::count())->toBe(0);
});

it('rechaza doble reserva superpuesta en la misma mesa (±2h)', function () {
    $mozo = makeReservasUser('Mozo');
    $clienteA = User::factory()->create(['name' => 'Cliente A']);
    $clienteB = User::factory()->create(['name' => 'Cliente B']);

    $mesa = Table::factory()->create([
        'number' => 3,
        'status' => Table::STATUS_RESERVED,
        'restaurant_id' => 1,
    ]);

    // Reserva existente en +3h
    Reservation::factory()->create([
        'table_id' => $mesa->id,
        'restaurant_id' => 1,
        'customer_id' => $clienteA->id,
        'status' => 'pending',
        'reservation_time' => now()->addHours(3),
    ]);

    // Intento de segunda reserva en +4h (dentro de la ventana ±2h)
    Livewire::actingAs($mozo)
        ->test(GestionReservas::class)
        ->set('modalNuevaReserva', true)
        ->set('nuevaCustomerId', $clienteB->id)
        ->set('nuevaTableId', $mesa->id)
        ->set('nuevaFecha', now()->addHours(4)->format('Y-m-d H:i:00'))
        ->set('nuevaPersonas', 2)
        ->call('crearReserva')
        ->assertNotified('No se pudo crear la reserva');

    expect(Reservation::count())->toBe(1);
});

// ─────────────────────────────────────────────────────────────
// MODAL: Editar Reserva (inline Stitch, sin página Filament)
// ─────────────────────────────────────────────────────────────

it('editar una reserva abre el modal inline con los datos precargados', function () {
    $mozo = makeReservasUser('Mozo');
    $cliente = User::factory()->create(['name' => 'Ana Hijo']);

    $mesa = Table::factory()->create(['number' => 9, 'capacity' => 8, 'status' => Table::STATUS_RESERVED, 'restaurant_id' => 1]);
    $reserva = Reservation::factory()->create([
        'table_id' => $mesa->id,
        'restaurant_id' => 1,
        'customer_id' => $cliente->id,
        'status' => 'pending',
        'reservation_time' => now()->addHours(3)->startOfHour(),
    ]);

    $component = Livewire::actingAs($mozo)->test(GestionReservas::class);

    // Abrir el modal
    $component->call('editReservation', $reserva->id);
    expect($component->get('reservaEditId'))->toBe($reserva->id)
        ->and($component->get('editCustomerId'))->toBe($cliente->id)
        ->and($component->get('editTableId'))->toBe($mesa->id)
        ->and($component->get('editPersonas'))->toBe($reserva->guest_count)
        ->and($component->get('editStatus'))->toBe('pending');

    // Guardar cambios: cambiar fecha/personas respetando validación atómica.
    $component->set('editFecha', now()->addHours(4)->format('Y-m-d H:i:00'))
        ->set('editPersonas', 6)
        ->call('guardarEdicionReserva')
        ->assertNotified('Reserva actualizada');

    $reserva->refresh();
    expect($reserva->guest_count)->toBe(6);
});
