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
use Illuminate\Support\Facades\Schema;
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
        'pos_x' => 0,
        'pos_y' => 0,
    ]);
});

it('alimenta pos_x/pos_y con default 0 al crear una mesa sin coordenadas', function () {
    // Defensa en profundidad: createTable() persiste 0 explícitamente, así el
    // insert nunca depende de que el default de DB esté aplicado en el schema.
    $mozo = makeMesaUser('Mozo');

    Livewire::actingAs($mozo)
        ->test(TableMap::class)
        ->set('newNumber', 43)
        ->set('newLocation', 'terraza')
        ->set('newCapacity', 2)
        ->call('createTable')
        ->assertHasNoErrors();

    $created = Table::where('restaurant_id', 1)->where('number', 43)->first();

    expect($created)->not->toBeNull()
        ->and($created->pos_x)->toBe(0)
        ->and($created->pos_y)->toBe(0);
});

it('las columnas pos_x/pos_y tienen default 0 en el schema (migración aplicada)', function () {
    // La migración add_default_coordinates_to_tables_table alinea el schema real:
    // en dev las columnas quedaron NOT NULL sin default y rompían la creación.
    $columns = collect(Schema::getColumns('tables'))->keyBy('name');

    // Postgres expone el default como string ('0'); comparamos normalizado.
    expect((string) $columns['pos_x']['default'])->toBe('0')
        ->and((string) $columns['pos_y']['default'])->toBe('0');
});

it('rechaza crear una mesa con número duplicado en el restaurante', function () {
    $mozo = makeMesaUser('Mozo');

    Table::factory()->create([
        'number' => 7,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ]);

    $component = Livewire::actingAs($mozo)
        ->test(TableMap::class)
        ->set('newNumber', 7)
        ->set('newLocation', 'salon')
        ->set('newCapacity', 4)
        ->call('createTable');

    $component->assertHasErrors(['newNumber']);

    // El error queda expuesto en el ErrorBag para que @error lo muestre en el modal.
    expect($component->instance()->getErrorBag()->has('newNumber'))->toBeTrue();
});

it('rechaza capacidad fuera del rango permitido (1-20) con error visible', function () {
    $mozo = makeMesaUser('Mozo');

    $component = Livewire::actingAs($mozo)
        ->test(TableMap::class)
        ->set('newNumber', 9)
        ->set('newLocation', 'salon')
        ->set('newCapacity', 21)
        ->call('createTable');

    $component
        ->assertHasErrors(['newCapacity'])
        ->assertNotSet('tablesByLocation.salon', fn ($tables) => collect($tables)->contains('number', 9));

    expect($component->instance()->getErrorBag()->has('newCapacity'))->toBeTrue();
});

it('rechaza una location inválida con error visible', function () {
    $mozo = makeMesaUser('Mozo');

    // 'patio' NO es una clave de ZONE_LABELS: la validación Rule::in debe rechazarla.
    Livewire::actingAs($mozo)
        ->test(TableMap::class)
        ->set('newNumber', 10)
        ->set('newLocation', 'patio')
        ->set('newCapacity', 4)
        ->call('createTable')
        ->assertHasErrors(['newLocation'])
        ->assertNotSet('tablesByLocation.salon', fn ($tables) => collect($tables)->contains('number', 10));
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

// ─────────────────────────────────────────────────────────────
// FIX 1 — FILTRO DE ZONA (regresión: plano vacío al alternar filtros)
// ─────────────────────────────────────────────────────────────

it('filtra el plano por zona y vuelve a "Todas" sin quedar vacío', function () {
    $mozo = makeMesaUser('Mozo');

    Table::factory()->create(['number' => 1, 'location' => 'Terraza', 'status' => Table::STATUS_AVAILABLE, 'restaurant_id' => 1]);
    Table::factory()->create(['number' => 2, 'location' => 'Salón', 'status' => Table::STATUS_AVAILABLE, 'restaurant_id' => 1]);
    Table::factory()->create(['number' => 3, 'location' => 'Barra', 'status' => Table::STATUS_AVAILABLE, 'restaurant_id' => 1]);

    $component = Livewire::actingAs($mozo)->test(TableMap::class);

    // Defecto: todas las zonas visibles.
    $component
        ->assertSeeHtml('data-zone-id="terraza"')
        ->assertSeeHtml('data-zone-id="salon"')
        ->assertSeeHtml('data-zone-id="barra"');

    // Solo Terraza.
    $component->set('activeZone', 'terraza')
        ->assertSeeHtml('data-zone-id="terraza"')
        ->assertDontSeeHtml('data-zone-id="salon"')
        ->assertDontSeeHtml('data-zone-id="barra"');

    // Solo Salón.
    $component->set('activeZone', 'salon')
        ->assertSeeHtml('data-zone-id="salon"')
        ->assertDontSeeHtml('data-zone-id="terraza"')
        ->assertDontSeeHtml('data-zone-id="barra"');

    // Solo Barra.
    $component->set('activeZone', 'barra')
        ->assertSeeHtml('data-zone-id="barra"')
        ->assertDontSeeHtml('data-zone-id="terraza"')
        ->assertDontSeeHtml('data-zone-id="salon"');

    // Regresión del bug: al volver a "Todas" todo el plano reaparece.
    $component->set('activeZone', 'all')
        ->assertSeeHtml('data-zone-id="terraza"')
        ->assertSeeHtml('data-zone-id="salon"')
        ->assertSeeHtml('data-zone-id="barra"');
});

it('visibleZones respeta la zona activa y omite zonas sin mesas', function () {
    $mozo = makeMesaUser('Mozo');

    Table::factory()->create(['number' => 10, 'location' => 'Terraza', 'status' => Table::STATUS_AVAILABLE, 'restaurant_id' => 1]);

    $component = Livewire::actingAs($mozo)->test(TableMap::class)->set('activeZone', 'terraza');
    $visible = $component->instance()->visibleZones;

    expect(array_keys($visible))->toBe(['terraza']);

    // Zona sin mesas (barra) nunca aparece, ni siquiera en "Todas".
    $component->set('activeZone', 'all');
    expect(array_keys($component->instance()->visibleZones))->not->toContain('barra');
});
