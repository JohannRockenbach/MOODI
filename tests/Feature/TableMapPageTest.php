<?php

namespace Tests\Feature;

use App\Filament\Pages\TableMap;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);
});

function makeMesaUser(string $role): User
{
    Role::findOrCreate($role, 'web');

    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

// ─────────────────────────────────────────────────────────────
// HAPPY PATH
// ─────────────────────────────────────────────────────────────

it('selecciona una mesa disponible y muestra sus especificaciones en el panel derecho', function () {
    $mozo = makeMesaUser('Mozo');

    $table = Table::factory()->create([
        'number' => 15,
        'capacity' => 4,
        'location' => 'Terraza',
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    $component = Livewire::actingAs($mozo)->test(TableMap::class);

    // Estado vacío inicial del panel
    $component->assertOk()->assertSee('Seleccioná una mesa para ver sus detalles');

    // Tocar la mesa → el panel derecho muestra las especificaciones (sin modal)
    $component
        ->call('selectTable', $table->id)
        ->assertSet('selectedTableId', $table->id)
        ->assertSee('#M-15')
        ->assertSee('4 Comensales')
        ->assertSee('Disponible');

    $selected = $component->instance()->selectedTable;

    expect($selected)->not->toBeNull()
        ->and($selected['number'])->toBe('15')
        ->and($selected['capacity'])->toBe(4)
        ->and($selected['status'])->toBe(Table::STATUS_AVAILABLE)
        ->and($selected['zone'])->toBe('terraza');
});

it('crea una mesa nueva desde el modal y aparece en el mapa', function () {
    $mozo = makeMesaUser('Mozo');

    Livewire::actingAs($mozo)
        ->test(TableMap::class)
        ->set('newNumber', 42)
        ->set('newLocation', 'barra')
        ->set('newCapacity', 1)
        ->call('createTable')
        ->assertHasNoErrors()
        ->assertSet('tablesByLocation.barra.0.number', 42);

    $this->assertDatabaseHas('tables', [
        'number' => 42,
        'location' => 'Barra',
        'capacity' => 1,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);
});

it('rechaza crear una mesa con número duplicado en el restaurante', function () {
    $mozo = makeMesaUser('Mozo');

    Table::factory()->create([
        'number' => 7,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    Livewire::actingAs($mozo)
        ->test(TableMap::class)
        ->set('newNumber', 7)
        ->set('newLocation', 'salon')
        ->set('newCapacity', 4)
        ->call('createTable')
        ->assertHasErrors(['newNumber']);
});

// ─────────────────────────────────────────────────────────────
// SEGURIDAD / AUTORIZACIÓN
// ─────────────────────────────────────────────────────────────

it('permite acceder al mapa solo a super_admin, Mozo y Cajero', function () {
    foreach (['super_admin', 'Mozo', 'Cajero'] as $role) {
        $user = makeMesaUser($role);
        auth()->login($user);

        expect(TableMap::canAccess())->toBeTrue("{$role} debería poder acceder al mapa");
    }
});

it('deniega acceso a Cocinero (canAccess false y 403 al montar la página)', function () {
    $cocinero = makeMesaUser('Cocinero');

    expect(TableMap::canAccess())->toBeFalse();

    Livewire::actingAs($cocinero)
        ->test(TableMap::class)
        ->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────
// EDGE CASES
// ─────────────────────────────────────────────────────────────

it('agrupa locations legacy en español bajo la zona normalizada', function () {
    $mozo = makeMesaUser('Mozo');

    Table::factory()->create([
        'number' => 20,
        'location' => 'Salón',
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);
    Table::factory()->create([
        'number' => 21,
        'location' => 'Barra',
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    $component = Livewire::actingAs($mozo)->test(TableMap::class);
    $byLocation = $component->get('tablesByLocation');

    expect(array_keys($byLocation))->toBe(['terraza', 'salon', 'barra'])
        ->and(collect($byLocation['salon'])->pluck('number'))->toContain('20')
        ->and(collect($byLocation['barra'])->pluck('number'))->toContain('21');
});

it('mesa ocupada sin pedidos activos no rompe KPIs ni POR COBRAR', function () {
    $mozo = makeMesaUser('Mozo');

    $table = Table::factory()->create([
        'number' => 5,
        'location' => 'Salón',
        'status' => Table::STATUS_OCCUPIED,
        'restaurant_id' => 1,
    ]);

    $component = Livewire::actingAs($mozo)->test(TableMap::class);
    $stats = $component->get('stats');

    expect($stats['occupied'])->toBe(1)
        ->and($stats['por_cobrar'])->toBe(0)
        ->and($stats['por_cobrar_total'])->toBe(0.0);

    $component->call('selectTable', $table->id);
    $selected = $component->instance()->selectedTable;

    expect($selected['orders_count'])->toBe(0)
        ->and($selected['first_order_id'])->toBeNull()
        ->and($selected['total_amount'])->toBe(0.0);
});

it('calcula POR COBRAR y permanencia con mesas ocupadas que tienen pedidos activos', function () {
    $mozo = makeMesaUser('Mozo');
    $waiter = makeMesaUser('Mozo');

    $table = Table::factory()->create([
        'number' => 30,
        'location' => 'Barra',
        'status' => Table::STATUS_OCCUPIED,
        'waiter_id' => $waiter->id,
        'restaurant_id' => 1,
    ]);

    $order = Order::factory()->create([
        'table_id' => $table->id,
        'restaurant_id' => 1,
        'status' => 'pending',
        'type' => 'salon',
        'waiter_id' => $waiter->id,
        'created_at' => now()->subMinutes(25),
    ]);

    $product = Product::factory()->create(['restaurant_id' => 1, 'price' => 10]);
    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 10,
    ]);

    $component = Livewire::actingAs($mozo)->test(TableMap::class);
    $stats = $component->get('stats');

    expect($stats['occupied'])->toBe(1)
        ->and($stats['por_cobrar'])->toBe(1)
        ->and($stats['por_cobrar_total'])->toBe(20.0)
        ->and($stats['avg_stay'])->toBe(25);

    $component->call('selectTable', $table->id);
    $selected = $component->instance()->selectedTable;

    expect($selected['orders_count'])->toBe(1)
        ->and($selected['first_order_id'])->toBe($order->id)
        ->and($selected['total_amount'])->toBe(20.0)
        ->and($selected['waiter_name'])->toBe($waiter->name);
});

it('el buscador filtra por número de mesa y excluye sin coincidencia', function () {
    $mozo = makeMesaUser('Mozo');

    Table::factory()->create([
        'number' => 77,
        'location' => 'Terraza',
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    $component = Livewire::actingAs($mozo)->test(TableMap::class);
    $enriched = collect($component->get('tablesByLocation')['terraza'])->first();

    $component->set('search', '77');
    expect($component->instance()->tableMatchesSearch($enriched))->toBeTrue();

    // Buscar sin resultados: la mesa queda fuera del filtro.
    $component->set('search', '999');
    expect($component->instance()->tableMatchesSearch($enriched))->toBeFalse();
});

it('muestra la próxima reserva futura como reservation_info', function () {
    $mozo = makeMesaUser('Mozo');
    $cliente = User::factory()->create(['name' => 'Familia Gómez']);

    $table = Table::factory()->create([
        'number' => 22,
        'location' => 'Salón',
        'status' => Table::STATUS_RESERVED,
        'restaurant_id' => 1,
    ]);

    $fecha = now()->addHour()->startOfHour();
    Reservation::factory()->create([
        'table_id' => $table->id,
        'restaurant_id' => 1,
        'customer_id' => $cliente->id,
        'status' => 'confirmed',
        'reservation_time' => $fecha,
    ]);

    $component = Livewire::actingAs($mozo)->test(TableMap::class);
    $component->call('selectTable', $table->id)->assertSee('Familia Gómez');

    $selected = $component->instance()->selectedTable;

    expect($selected['has_reservation'])->toBeTrue()
        ->and($selected['reservation_info'])->toBe('Familia Gómez ('.$fecha->format('H:i').'h)');
});

it('una mesa en mantenimiento no se puede ocupar', function () {
    $table = Table::factory()->create([
        'status' => Table::STATUS_MAINTENANCE,
        'restaurant_id' => 1,
    ]);

    expect($table->occupy())->toBeFalse();
    expect($table->fresh()->status)->toBe(Table::STATUS_MAINTENANCE);
});
