<?php

namespace Tests\Feature;

use App\Filament\Pages\GestionMesas;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * GestionMesas — Testing Strategy B del proyecto:
 * 1) happy path de acceso/render y creación con validaciones,
 * 2) seguridad/autorización por rol (Cocinero denegado, 403),
 * 3) edge cases: eliminación bloqueada con pedidos o reservas activas
 *    (validación NUEVA de esta pantalla) y permisos de super_admin.
 */
beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function makeMesasUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

// ─────────────────────────────────────────────────────────────
// HAPPY PATH
// ─────────────────────────────────────────────────────────────

it('renderiza el listado de mesas con KPIs y tarjetas (Mozo)', function () {
    $mozo = makeMesasUser('Mozo');
    $waiter = makeMesasUser('Mozo');

    Table::factory()->create([
        'id' => 15,
        'number' => 15,
        'capacity' => 4,
        'location' => 'Terraza',
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);
    Table::factory()->create([
        'id' => 16,
        'number' => 16,
        'capacity' => 2,
        'location' => 'Barra',
        'status' => Table::STATUS_OCCUPIED,
        'waiter_id' => $waiter->id,
        'restaurant_id' => 1,
    ]);

    Livewire::actingAs($mozo)
        ->test(GestionMesas::class)
        ->assertOk()
        ->assertSee('DISPONIBLES')
        ->assertSee('OCUPADAS')
        ->assertSee('RESERVADAS')
        ->assertSee('mesa-15', false)
        ->assertSee('mesa-16', false)
        ->assertSee('Terraza')
        ->assertSee($waiter->name);

    $stats = Livewire::actingAs($mozo)->test(GestionMesas::class)->get('stats');

    expect($stats['available'])->toBe(1)
        ->and($stats['occupied'])->toBe(1)
        ->and($stats['reserved'])->toBe(0)
        ->and($stats['maintenance'])->toBe(0);
});

it('Cajero también accede al listado (TablePolicy::viewAny)', function () {
    $cajero = makeMesasUser('Cajero');
    auth()->login($cajero);

    expect(GestionMesas::canAccess())->toBeTrue();

    Livewire::actingAs($cajero)
        ->test(GestionMesas::class)
        ->assertOk();
});

it('crea una mesa nueva desde el modal con zona normalizada', function () {
    $mozo = makeMesasUser('Mozo');

    Livewire::actingAs($mozo)
        ->test(GestionMesas::class)
        ->set('newNumber', 42)
        ->set('newLocation', 'barra')
        ->set('newCapacity', 1)
        ->call('createTable')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tables', [
        'number' => 42,
        'location' => 'Barra',
        'capacity' => 1,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);
});

it('rechaza número duplicado en el restaurante al crear mesa', function () {
    $mozo = makeMesasUser('Mozo');

    Table::factory()->create([
        'number' => 7,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    Livewire::actingAs($mozo)
        ->test(GestionMesas::class)
        ->set('newNumber', 7)
        ->set('newLocation', 'salon')
        ->set('newCapacity', 4)
        ->call('createTable')
        ->assertHasErrors(['newNumber']);
});

// ─────────────────────────────────────────────────────────────
// SEGURIDAD / AUTORIZACIÓN
// ─────────────────────────────────────────────────────────────

it('permite acceder solo a super_admin, Mozo y Cajero', function () {
    foreach (['super_admin', 'Mozo', 'Cajero'] as $role) {
        $user = makeMesasUser($role);
        auth()->login($user);

        expect(GestionMesas::canAccess())->toBeTrue("{$role} debería poder acceder");
    }
});

it('deniega acceso a Cocinero (canAccess false y 403 al montar)', function () {
    $cocinero = makeMesasUser('Cocinero');

    expect(GestionMesas::canAccess())->toBeFalse();

    Livewire::actingAs($cocinero)
        ->test(GestionMesas::class)
        ->assertStatus(403);
});

it('deniega eliminar mesas a un Mozo (solo super_admin)', function () {
    $mozo = makeMesasUser('Mozo');

    $table = Table::factory()->create([
        'number' => 30,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    Livewire::actingAs($mozo)
        ->test(GestionMesas::class)
        ->call('deleteTable', $table->id)
        ->assertNotified('Permiso denegado');

    expect(Table::find($table->id))->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────
// EDGE CASES
// ─────────────────────────────────────────────────────────────

it('bloquea eliminar una mesa con reservas activas (validación nueva)', function () {
    $admin = makeMesasUser('super_admin');
    $cliente = User::factory()->create(['name' => 'Familia Pérez']);

    $table = Table::factory()->create([
        'number' => 10,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    // Crear la reserva dispara el ReservationObserver (mesa → reserved).
    Reservation::factory()->create([
        'table_id' => $table->id,
        'restaurant_id' => 1,
        'customer_id' => $cliente->id,
        'status' => 'pending',
        'reservation_time' => now()->addDay(),
    ]);

    Livewire::actingAs($admin)
        ->test(GestionMesas::class)
        ->call('deleteTable', $table->id)
        ->assertNotified('No se puede eliminar');

    expect(Table::find($table->id))->not->toBeNull();
});

it('bloquea eliminar una mesa con pedidos asociados (integridad de datos)', function () {
    $admin = makeMesasUser('super_admin');

    $table = Table::factory()->create([
        'number' => 11,
        'status' => Table::STATUS_OCCUPIED,
        'restaurant_id' => 1,
    ]);

    // Pedido ya completado: aún así la mesa tiene historial → no se elimina.
    Order::factory()->create([
        'table_id' => $table->id,
        'restaurant_id' => 1,
        'status' => 'completed',
        'type' => 'salon',
        'waiter_id' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test(GestionMesas::class)
        ->call('deleteTable', $table->id)
        ->assertNotified('No se puede eliminar');

    expect(Table::find($table->id))->not->toBeNull();
});

it('super_admin puede eliminar una mesa sin pedidos ni reservas', function () {
    $admin = makeMesasUser('super_admin');

    $table = Table::factory()->create([
        'number' => 12,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    Livewire::actingAs($admin)
        ->test(GestionMesas::class)
        ->call('deleteTable', $table->id)
        ->assertNotified('Mesa eliminada');

    expect(Table::find($table->id))->toBeNull();
});

it('no cambia estado de mesas ocupadas ni con pedidos activos (guardas de toggle)', function () {
    $admin = makeMesasUser('super_admin');

    $ocupada = Table::factory()->create([
        'number' => 20,
        'status' => Table::STATUS_OCCUPIED,
        'restaurant_id' => 1,
    ]);
    Order::factory()->create([
        'table_id' => $ocupada->id,
        'restaurant_id' => 1,
        'status' => 'pending',
        'type' => 'salon',
    ]);

    Livewire::actingAs($admin)
        ->test(GestionMesas::class)
        ->call('toggleTableStatus', $ocupada->id)
        ->assertNotified('No se puede cambiar el estado');

    expect($ocupada->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

it('toggle disponible ↔ mantenimiento funciona solo en mesas disponibles', function () {
    $admin = makeMesasUser('super_admin');

    $disponible = Table::factory()->create([
        'number' => 21,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    Livewire::actingAs($admin)
        ->test(GestionMesas::class)
        ->call('toggleTableStatus', $disponible->id);

    expect($disponible->fresh()->status)->toBe(Table::STATUS_MAINTENANCE);
});