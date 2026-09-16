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

function makePosUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function makePosProduct(string $name = 'Burger Clásica', array $overrides = []): Product
{
    $category = Category::firstOrCreate(['name' => 'Hamburguesas']);

    return Product::factory()->create(array_merge([
        'name' => $name,
        'price' => 1000.00,
        'is_available' => true,
        'category_id' => $category->id,
        'restaurant_id' => 1,
        'recipe_id' => null, // sin receta → realStock = stock almacenado
        'stock' => 10,
    ], $overrides));
}

function makePosTable(array $overrides = []): Table
{
    return Table::factory()->create(array_merge([
        'number' => 22,
        'capacity' => 4,
        'location' => 'Salón Principal',
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ], $overrides));
}

// ─────────────────────────────────────────────────────────────
// HAPPY PATH
// ─────────────────────────────────────────────────────────────

it('crea un pedido real con 2 items, ocupa la mesa y registra al mozo', function () {
    $mozo = makePosUser('Mozo');

    $burger = makePosProduct('Burger Clásica', ['price' => 1000.00]);
    $coca = makePosProduct('Coca-Cola 350ml', ['price' => 500.00, 'stock' => 50]);
    $table = makePosTable();

    $component = Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CrearPedido::class)
        ->assertSet('selectedTableId', $table->id)
        ->assertSet('tableLockedFromMap', true)
        ->call('addItem', $burger->id)
        ->call('addItem', $coca->id)
        ->call('addItem', $burger->id); // burger x2

    expect($component->instance()->subtotal)->toBe(2500.0)
        ->and($component->instance()->itemCount)->toBe(3);

    $component->call('submitOrder')
        ->assertHasNoErrors()
        ->assertSet('items', []);

    $order = Order::where('restaurant_id', 1)->where('table_id', $table->id)->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe('pending')
        ->and($order->type)->toBe('salon')
        ->and($order->table_id)->toBe($table->id)
        ->and($order->waiter_id)->toBe($mozo->id)
        ->and($order->stock_deducted)->toBeFalse()
        ->and($order->orderProducts()->count())->toBe(2);

    $burgerLine = $order->orderProducts()->where('product_id', $burger->id)->first();
    $cocaLine = $order->orderProducts()->where('product_id', $coca->id)->first();

    expect((int) $burgerLine->quantity)->toBe(2)
        ->and((float) $burgerLine->price)->toBe(1000.0)
        ->and((int) $cocaLine->quantity)->toBe(1)
        ->and((float) $cocaLine->price)->toBe(500.0);

    // La mesa quedó ocupada (máquina de estados).
    expect($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

it('crea un pedido para llevar sin mesa cuando se elige esa modalidad', function () {
    $mozo = makePosUser('Mozo');
    $burger = makePosProduct();

    Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->call('setOrderType', CrearPedido::TYPE_TAKEAWAY)
        ->call('addItem', $burger->id)
        ->call('submitOrder')
        ->assertHasNoErrors();

    $order = Order::where('type', 'para_llevar')->first();

    expect($order)->not->toBeNull()
        ->and($order->type)->toBe('para_llevar')
        ->and($order->table_id)->toBeNull()
        ->and($order->status)->toBe('pending')
        ->and($order->waiter_id)->toBe($mozo->id);
});

// ─────────────────────────────────────────────────────────────
// SEGURIDAD / AUTORIZACIÓN
// ─────────────────────────────────────────────────────────────

it('permite el acceso solo a super_admin y Mozo', function () {
    foreach (['super_admin', 'Mozo'] as $role) {
        $this->actingAs(makePosUser($role));
        expect(CrearPedido::canAccess())->toBeTrue();
    }

    foreach (['Cajero', 'Cocinero'] as $role) {
        $this->actingAs(makePosUser($role));
        expect(CrearPedido::canAccess())->toBeFalse();
    }
});

it('deniega con 403 la ruta para Cajero y Cocinero', function () {
    foreach (['Cajero', 'Cocinero'] as $role) {
        $this->actingAs(makePosUser($role))
            ->get(CrearPedido::getUrl())
            ->assertForbidden();
    }
});

// ─────────────────────────────────────────────────────────────
// EDGE CASES
// ─────────────────────────────────────────────────────────────

it('no permite enviar una comanda vacía', function () {
    $mozo = makePosUser('Mozo');
    $table = makePosTable();

    Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CrearPedido::class)
        ->call('submitOrder')
        ->assertHasErrors(['items']);

    expect(Order::count())->toBe(0);
});

it('no agrega un producto con is_available=false', function () {
    $mozo = makePosUser('Mozo');
    makePosProduct('Producto Oculto', ['is_available' => false]);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);

    $product = Product::where('is_available', false)->first();

    $component->call('addItem', $product->id)
        ->assertSet('items', [])
        ->assertSet('itemCount', 0);

    expect($component->instance()->items)->toBe([]);
});

// ─────────────────────────────────────────────────────────────
// CATÁLOGO EN SECCIONES: COMIDA / BEBIDAS
// ─────────────────────────────────────────────────────────────

it('muestra solo comida en la pestaña Comida y bebidas en Bebidas', function () {
    $mozo = makePosUser('Mozo');
    $burger = makePosProduct('Burger Clásica'); // Hamburguesas → Comida
    $papas = makePosProduct('Papas con Cheddar', [
        'category_id' => Category::firstOrCreate(['name' => 'Papas Fritas'])->id,
    ]);
    $coca = makePosProduct('Coca-Cola 350ml', [
        'price' => 500.00,
        'stock' => 50,
        'category_id' => Category::firstOrCreate(['name' => 'Bebidas'])->id,
    ]);

    // Comida: hamburguesas y papas, NUNCA bebidas.
    $food = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->call('setSection', CrearPedido::SECTION_FOOD)
        ->assertSet('activeSection', CrearPedido::SECTION_FOOD);

    expect($food->instance()->catalogProducts->pluck('id')->all())
        ->toContain($burger->id)
        ->toContain($papas->id)
        ->not->toContain($coca->id);

    // Bebidas: solo bebidas.
    $drinks = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->call('setSection', CrearPedido::SECTION_DRINKS)
        ->assertSet('activeSection', CrearPedido::SECTION_DRINKS);

    expect($drinks->instance()->catalogProducts->pluck('id')->all())
        ->toContain($coca->id)
        ->not->toContain($burger->id)
        ->not->toContain($papas->id);

    // Todo: el catálogo completo.
    $all = Livewire::actingAs($mozo)->test(CrearPedido::class);

    expect(count($all->instance()->catalogProducts))->toBe(3);
});

it('mapea categorías con cerveza en el nombre como Bebidas', function () {
    $mozo = makePosUser('Mozo');
    $beer = makePosProduct('IPA Artesanal 500ml', [
        'category_id' => Category::firstOrCreate(['name' => 'Cervezas'])->id,
    ]);

    $drinks = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->call('setSection', CrearPedido::SECTION_DRINKS);

    expect($drinks->instance()->catalogProducts->pluck('id')->all())
        ->toContain($beer->id);
});

// ─────────────────────────────────────────────────────────────
// GRILLA TÁCTIL DE MESAS
// ─────────────────────────────────────────────────────────────

it('selecciona una mesa desde la grilla táctil del modal', function () {
    $mozo = makePosUser('Mozo');
    $table = makePosTable(['number' => 12, 'location' => 'Terraza']);

    $component = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->call('openTableModal')
        ->assertSet('showTableModal', true);

    // En el modal la tarjeta setea newTableId y "Aplicar Mesa" confirma.
    $component->set('newTableId', $table->id)
        ->call('confirmTableChange')
        ->assertSet('showTableModal', false)
        ->assertSet('selectedTableId', $table->id);

    expect($component->instance()->selectedTable->number)->toBe('12');
});

// ─────────────────────────────────────────────────────────────
// EDGE CASES
// ─────────────────────────────────────────────────────────────

it('muestra la grilla vacía con aviso cuando no hay mesas', function () {
    $mozo = makePosUser('Mozo');

    Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->call('openTableModal')
        ->assertSet('showTableModal', true)
        ->assertSee('No hay mesas cargadas');
});

it('no agrega un producto sin stock real (realStock <= 0)', function () {
    $mozo = makePosUser('Mozo');
    makePosProduct('Agotado', ['stock' => 0]);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);

    $product = Product::where('name', 'Agotado')->first();

    $component->call('addItem', $product->id)
        ->assertSet('items', []);

    expect($component->instance()->items)->toBe([]);
});

it('exige mesa cuando la modalidad es salón', function () {
    $mozo = makePosUser('Mozo');
    $burger = makePosProduct();

    Livewire::actingAs($mozo)
        ->test(CrearPedido::class) // sin ?table_id → mesa libre
        ->assertSet('selectedTableId', null)
        ->call('addItem', $burger->id)
        ->call('submitOrder')
        ->assertHasErrors(['selectedTableId']);

    expect(Order::count())->toBe(0);
});

it('suma el pedido a una mesa ya ocupada sin pisar su estado', function () {
    $mozo = makePosUser('Mozo');
    $burger = makePosProduct();
    $table = makePosTable(['status' => Table::STATUS_OCCUPIED]);

    Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CrearPedido::class)
        ->call('addItem', $burger->id)
        ->call('submitOrder')
        ->assertHasNoErrors();

    $order = Order::where('table_id', $table->id)->first();

    expect($order)->not->toBeNull()
        ->and($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

it('ignora una mesa de otro restaurante en la URL', function () {
    $mozo = makePosUser('Mozo');
    Restaurant::factory()->create(['id' => 999]);
    $foreignTable = Table::factory()->create([
        'number' => 99,
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 999,
    ]);

    Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $foreignTable->id])
        ->test(CrearPedido::class)
        ->assertSet('selectedTableId', null)
        ->assertSet('tableLockedFromMap', false);
});

it('bloquea cambiar de modalidad y de mesa cuando viene del mapa', function () {
    $mozo = makePosUser('Mozo');
    $table = makePosTable();
    $otherTable = makePosTable(['number' => 23]);

    Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CrearPedido::class)
        ->call('setOrderType', CrearPedido::TYPE_TAKEAWAY)
        ->assertSet('orderType', 'salon') // bloqueado
        ->call('selectTable', $otherTable->id)
        ->assertSet('selectedTableId', $table->id) // bloqueado
        ->call('openTableModal')
        ->assertSet('showTableModal', false); // sin modal de cambio
});
it('renderiza la grilla de mesas al entrar sin mesa precargada', function () {
    $mozo = makePosUser('Mozo');
    $table = makePosTable(['number' => 36, 'location' => 'Terraza']);

    // Sin ?table_id → el TPV muestra el botón "Elegí una mesa" (grilla táctil);
    // ya no hay <select>/<option>. La grilla se ve al abrir el modal.
    Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->assertSet('selectedTableId', null)
        ->assertSee('Elegí una mesa')
        ->assertDontSee('<option')
        ->call('openTableModal')
        ->assertSet('showTableModal', true)
        ->assertSee('Terraza')
        ->assertSee('36'); // número GRANDE de la tarjeta en la grilla
});
