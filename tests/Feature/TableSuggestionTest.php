<?php

namespace Tests\Feature;

use App\Filament\Pages\CrearPedido;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function suggestionUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function suggestionTable(array $overrides = []): Table
{
    return Table::factory()->create(array_merge([
        'number' => '1',
        'capacity' => 4,
        'location' => 'Salón Principal',
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ], $overrides));
}

function suggestionProduct(string $name = 'Burger Clásica'): Product
{
    return Product::factory()->create([
        'name' => $name,
        'price' => 1000.00,
        'is_available' => true,
        'category_id' => Category::firstOrCreate(['name' => 'Hamburguesas'])->id,
        'restaurant_id' => 1,
        'recipe_id' => null, // sin receta → realStock = stock almacenado
        'stock' => 10,
    ]);
}

// ─────────────────────────────────────────────────────────────
// HAPPY PATH
// ─────────────────────────────────────────────────────────────

it('con pax=4 sugiere primero las mesas libres con capacidad suficiente', function () {
    $mozo = suggestionUser('Mozo');

    $small = suggestionTable(['number' => '1', 'capacity' => 2]);
    $mid = suggestionTable(['number' => '2', 'capacity' => 4]);
    $large = suggestionTable(['number' => '3', 'capacity' => 8]);
    $occupied = suggestionTable(['number' => '4', 'capacity' => 10, 'status' => Table::STATUS_OCCUPIED]);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);
    $component->set('guestCount', 4);

    $tables = $component->instance()->suggestedTables;

    // Sugeridas: libres con capacity >= 4 (mid y large). Ordenadas por capacity asc.
    expect($tables->pluck('id')->all())
        ->toBe([$mid->id, $large->id, $small->id, $occupied->id]);

    // Flags: solo libres con capacity >= pax llevan is_suggested = true.
    expect($tables->firstWhere('id', $mid->id)['is_suggested'])->toBeTrue()
        ->and($tables->firstWhere('id', $large->id)['is_suggested'])->toBeTrue()
        ->and($tables->firstWhere('id', $small->id)['is_suggested'])->toBeFalse()
        ->and($tables->firstWhere('id', $occupied->id)['is_suggested'])->toBeFalse();
});

// ─────────────────────────────────────────────────────────────
// SEGURIDAD / AUTORIZACIÓN
// ─────────────────────────────────────────────────────────────

it('deniega con 403 la ruta del TPV a Cajero y Cocinero', function () {
    foreach (['Cajero', 'Cocinero'] as $role) {
        $this->actingAs(suggestionUser($role))
            ->get(CrearPedido::getUrl())
            ->assertForbidden();
    }
});

// ─────────────────────────────────────────────────────────────
// EDGE CASES
// ─────────────────────────────────────────────────────────────

it('sin comensales (null o 0) no ordena ni sugiere', function () {
    $mozo = suggestionUser('Mozo');

    $a = suggestionTable(['number' => '1', 'capacity' => 2]);
    $b = suggestionTable(['number' => '2', 'capacity' => 8]);

    // guestCount null (por defecto): todas en orden natural, sin sugerencia.
    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);

    $tables = $component->instance()->suggestedTables;

    expect($tables->pluck('id')->all())->toBe([$a->id, $b->id])
        ->and($tables->every(fn (array $t) => $t['is_suggested'] === false))->toBeTrue();

    // guestCount = 0: mismo comportamiento.
    $component->set('guestCount', 0);
    $tables = $component->instance()->suggestedTables;

    expect($tables->pluck('id')->all())->toBe([$a->id, $b->id])
        ->and($tables->every(fn (array $t) => $t['is_suggested'] === false))->toBeTrue();
});

it('con pax mayor a la mesa más grande no bloquea ni sugiere', function () {
    $mozo = suggestionUser('Mozo');

    $small = suggestionTable(['number' => '1', 'capacity' => 2]);
    $medium = suggestionTable(['number' => '2', 'capacity' => 4]);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);
    $component->set('guestCount', 10);

    $tables = $component->instance()->suggestedTables;

    // Sigue listando todas las libres, ninguna marcada como sugerida.
    expect($tables->pluck('id')->all())->toContain($small->id)->toContain($medium->id)
        ->and($tables->every(fn (array $t) => $t['is_suggested'] === false))->toBeTrue();
});

it('guarda guest_count al crear un pedido de salón con comensales', function () {
    $mozo = suggestionUser('Mozo');
    $burger = suggestionProduct();
    $table = suggestionTable(['number' => '22']);

    Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CrearPedido::class)
        ->set('guestCount', 5)
        ->call('addItem', $burger->id)
        ->call('submitOrder')
        ->assertHasNoErrors();

    $order = Order::where('table_id', $table->id)->first();

    expect($order)->not->toBeNull()
        ->and($order->type)->toBe('salon')
        ->and($order->guest_count)->toBe(5);
});

it('guarda guest_count null en un pedido de salón sin comensales', function () {
    $mozo = suggestionUser('Mozo');
    $burger = suggestionProduct();
    $table = suggestionTable(['number' => '22']);

    Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CrearPedido::class)
        ->call('addItem', $burger->id)
        ->call('submitOrder')
        ->assertHasNoErrors();

    $order = Order::where('table_id', $table->id)->first();

    expect($order)->not->toBeNull()
        ->and($order->guest_count)->toBeNull();
});

it('no guarda guest_count en pedidos para llevar', function () {
    $mozo = suggestionUser('Mozo');
    $burger = suggestionProduct();

    Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->call('setOrderType', CrearPedido::TYPE_TAKEAWAY)
        ->set('guestCount', 7)
        ->call('addItem', $burger->id)
        ->call('submitOrder')
        ->assertHasNoErrors();

    $takeaway = Order::where('type', 'para_llevar')->first();

    expect($takeaway)->not->toBeNull()
        ->and($takeaway->table_id)->toBeNull()
        ->and($takeaway->guest_count)->toBeNull();
});
