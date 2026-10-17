<?php

namespace Tests\Feature;

use App\Filament\Pages\GestionPedidos;
use App\Filament\Resources\OrderResource;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * GestionPedidos — Testing Strategy B del proyecto:
 * 1) happy path de acceso/render (Mozo ve pedidos con totales y ítems),
 * 2) seguridad/autorización (Cajero denegado, 403),
 * 3) edge cases: el observer descuenta stock al pasar a processing,
 *    NO se completa desde pending, y el cobro reutiliza el modal.
 */
beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function makePedidosUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function makeProductoConReceta(): Product
{
    $category = Category::factory()->create(['name' => 'Comida']);

    $recipe = Recipe::create([
        'name' => 'Receta de prueba',
        'instructions' => 'Preparar.',
    ]);
    $ingredient = Ingredient::create([
        'name' => 'Insumo '.uniqid(),
        'current_stock' => 100,
        'measurement_unit' => 'kg',
        'reorder_point' => 0,
        'restaurant_id' => 1,
    ]);
    $recipe->ingredients()->attach($ingredient->id, ['required_amount' => 1]);

    return Product::factory()->forRestaurant(1)->available()->create([
        'category_id' => $category->id,
        'recipe_id' => $recipe->id,
        'price' => 2500,
        'stock' => 20,
    ]);
}

function makePedidoParaLlevar(Product $product, int $quantity = 1): Order
{
    $order = Order::factory()->forRestaurant(1)->pending()->create([
        'type' => 'para_llevar',
        'table_id' => null,
        'waiter_id' => User::factory()->create(['restaurant_id' => 1])->id,
        'customer_name' => 'Juan Pérez',
    ]);
    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => $quantity,
        'price' => $product->price,
        'notes' => null,
    ]);

    return $order;
}

// ─────────────────────────────────────────────────────────────
// HAPPY PATH
// ─────────────────────────────────────────────────────────────

it('renderiza el listado de pedidos con KPIs, totales y ítems (Mozo)', function () {
    $mozo = makePedidosUser('Mozo');

    $product = makeProductoConReceta();
    $pedido = makePedidoParaLlevar($product, 2);

    Livewire::actingAs($mozo)
        ->test(GestionPedidos::class)
        ->assertOk()
        ->assertSee('#'.$pedido->id)
        ->assertSee('PENDIENTES')
        ->assertSee('$ 5.000,00') // 2 x 2500 formateado AR
        ->assertSee('Juan Pérez')
        ->assertSee($product->name);

    $stats = Livewire::actingAs($mozo)->test(GestionPedidos::class)->get('stats');

    expect($stats['pending'])->toBe(1)
        ->and($stats['processing'])->toBe(0)
        ->and($stats['completed'])->toBe(0);
});

it('excluye los ítems de la categoría Bebidas de las tarjetas', function () {
    $mozo = makePedidosUser('Mozo');
    $comida = Category::factory()->create(['name' => 'Comida']);
    $bebidas = Category::factory()->create(['name' => 'Bebidas']);

    $productoComida = Product::factory()->forRestaurant(1)->available()->create([
        'category_id' => $comida->id,
        'price' => 100,
    ]);
    $productoBebida = Product::factory()->forRestaurant(1)->available()->create([
        'category_id' => $bebidas->id,
        'price' => 50,
    ]);

    $order = Order::factory()->forRestaurant(1)->pending()->create([
        'type' => 'para_llevar',
        'table_id' => null,
        'waiter_id' => $mozo->id,
        'customer_name' => 'Ana',
    ]);
    // El factory crea 1-5 ítems aleatorios en afterCreating; descartarlos
    // para que el test cuente exactamente los 2 explícitos.
    $order->orderProducts()->delete();
    $order->orderProducts()->create(['product_id' => $productoComida->id, 'quantity' => 1, 'price' => 100]);
    $order->orderProducts()->create(['product_id' => $productoBebida->id, 'quantity' => 3, 'price' => 50]);

    $instance = Livewire::actingAs($mozo)->test(GestionPedidos::class)->instance();
    $orderData = collect($instance->orders)->firstWhere('id', $order->id);

    expect($orderData['items'])
        ->toHaveCount(1)
        ->and($orderData['items'][0]['name'])->toBe($productoComida->name)
        // El total SÍ incluye las bebidas (el cobro/venta usa todo).
        ->and($orderData['total'])->toBe(250.0);
});

it('conserva el item de navegación "Cocina" para super_admin y Cocinero (vía RecetasCocina)', function () {
    // TAREA 1/2: OrderResource tiene shouldRegisterNavigation = false (su
    // getNavigationItems() NO se invoca) y GestionPedidos ya no registra el
    // item para Cocinero. El item "Cocina" lo registra RecetasCocina, la
    // página de cocina a la que el Cocinero SÍ accede (super_admin/Cocinero).
    $admin = makePedidosUser('super_admin');
    auth()->login($admin);

    $urls = collect(\App\Filament\Pages\RecetasCocina::getNavigationItems())
        ->map(fn ($item) => $item->getUrl());

    expect($urls)->toContain(OrderResource::getUrl('kitchen'));

    $cocinero = makePedidosUser('Cocinero');
    auth()->login($cocinero);

    $labels = collect(\App\Filament\Pages\RecetasCocina::getNavigationItems())
        ->map(fn ($item) => $item->getLabel());

    expect($labels)->toContain('Cocina');
});

it('un Mozo no ve el item "Cocina" en el menú real del panel', function () {
    $mozo = makePedidosUser('Mozo');
    auth()->login($mozo);

    $labels = collect(\Filament\Facades\Filament::getPanel('admin')->getNavigation())
        ->flatMap(fn ($group) => collect($group->getItems()))
        ->map(fn ($item) => $item->getLabel());

    expect($labels)->not->toContain('Cocina');
});

// ─────────────────────────────────────────────────────────────
// SEGURIDAD / AUTORIZACIÓN
// ─────────────────────────────────────────────────────────────

it('permite acceder solo a super_admin y Mozo (TAREA 1: el Cocinero ya no opera pedidos)', function () {
    foreach (['super_admin', 'Mozo'] as $role) {
        $user = makePedidosUser($role);
        auth()->login($user);

        expect(GestionPedidos::canAccess())->toBeTrue("{$role} debería poder acceder");
    }

    // TAREA 1: el Cocinero quedó FUERA de GestionPedidos (su mundo es
    // Cocina + Recetas; tampoco debe poder cobrar ni ver el listado).
    $cocinero = makePedidosUser('Cocinero');
    auth()->login($cocinero);

    expect(GestionPedidos::canAccess())->toBeFalse();
});

it('deniega acceso a Cajero (canAccess false y 403 al montar)', function () {
    $cajero = makePedidosUser('Cajero');

    expect(GestionPedidos::canAccess())->toBeFalse();

    Livewire::actingAs($cajero)
        ->test(GestionPedidos::class)
        ->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────
// EDGE CASES
// ─────────────────────────────────────────────────────────────

it('al pasar a processing el observer descuenta el stock automáticamente', function () {
    $mozo = makePedidosUser('Mozo');
    $product = makeProductoConReceta();
    $pedido = makePedidoParaLlevar($product, 2);

    Livewire::actingAs($mozo)
        ->test(GestionPedidos::class)
        ->call('startProcessing', $pedido->id)
        ->assertHasNoErrors();

    expect($pedido->fresh()->status)->toBe('processing')
        ->and($pedido->fresh()->stock_deducted)->toBeTrue();
});

it('no permite completar un pedido desde pending (cadena del KDS)', function () {
    $mozo = makePedidosUser('Mozo');
    $product = makeProductoConReceta();
    $pedido = makePedidoParaLlevar($product);

    Livewire::actingAs($mozo)
        ->test(GestionPedidos::class)
        ->call('markAsCompleted', $pedido->id)
        ->assertHasNoErrors();

    expect($pedido->fresh()->status)->toBe('pending');
});

it('marca listo y completado solo siguiendo la cadena processing → ready → completed', function () {
    $mozo = makePedidosUser('Mozo');
    $product = makeProductoConReceta();
    $pedido = makePedidoParaLlevar($product);

    Livewire::actingAs($mozo)
        ->test(GestionPedidos::class)
        ->call('startProcessing', $pedido->id)
        ->call('markAsReady', $pedido->id)
        ->call('markAsCompleted', $pedido->id);

    expect($pedido->fresh()->status)->toBe('completed');
});

it('cancelar un pedido en processing lo deja cancelled sin devolver stock (merma)', function () {
    $mozo = makePedidosUser('Mozo');
    $product = makeProductoConReceta();
    $pedido = makePedidoParaLlevar($product);

    $component = Livewire::actingAs($mozo)->test(GestionPedidos::class);
    $component->call('startProcessing', $pedido->id);
    $component->call('cancelOrder', $pedido->id);

    $order = $pedido->fresh();

    expect($order->status)->toBe('cancelled')
        // El stock ya fue descontado al pasar a processing: la cancelación
        // NO lo restaura (merma).
        ->and($order->stock_deducted)->toBeTrue();
});

it('cobrar un pedido para llevar reutiliza el modal de cobro (HasCobroRapido)', function () {
    $mozo = makePedidosUser('Mozo');
    $product = makeProductoConReceta();
    $pedido = makePedidoParaLlevar($product);

    Livewire::actingAs($mozo)
        ->test(GestionPedidos::class)
        ->call('cobrarPedido', $pedido->id)
        ->assertSet('open', true)
        ->assertSet('selectedOrderId', $pedido->id);
});

it('un pedido de salón cobra por mesa (abre el modal con la mesa)', function () {
    $mozo = makePedidosUser('Mozo');

    $mesa = Table::factory()->create([
        'number' => 5,
        'status' => Table::STATUS_OCCUPIED,
        'restaurant_id' => 1,
    ]);

    $pedido = Order::factory()->forRestaurant(1)->pending()->create([
        'type' => 'salon',
        'table_id' => $mesa->id,
        'waiter_id' => $mozo->id,
    ]);
    $pedido->orderProducts()->create([
        'product_id' => makeProductoConReceta()->id,
        'quantity' => 1,
        'price' => 100,
    ]);

    Livewire::actingAs($mozo)
        ->test(GestionPedidos::class)
        ->call('cobrarPedido', $pedido->id)
        ->assertSet('open', true)
        ->assertSet('selectedTableId', $mesa->id);
});

it('no permite montar Pedidos a un Cocinero (403): no ve pedidos ni puede cobrar', function () {
    $cocinero = makePedidosUser('Cocinero');

    expect($cocinero->can('create', \App\Models\Sale::class))->toBeFalse();

    // TAREA 1: la página de Pedidos deniega el acceso completo al Cocinero
    // (canAccess false → el mount aborta 403): no ve el listado de pedidos
    // ni el botón Cobrar. La defensa en profundidad de cobrar() (403 para
    // Cocinero) se prueba en CobroRapidoTest.
    Livewire::actingAs($cocinero)
        ->test(GestionPedidos::class)
        ->assertStatus(403);

    // El helper de UI (puedeCobrar) es del rol: un Mozo (que sí opera el
    // salón) puede cobrar; el Cocinero no tiene ningún rol de cobro.
    $mozo = makePedidosUser('Mozo');
    $component = Livewire::actingAs($mozo)->test(GestionPedidos::class);

    expect($component->instance()->puedeCobrar())->toBeTrue()
        ->and($cocinero->hasAnyRole(['super_admin', 'Mozo', 'Cajero']))->toBeFalse();
});
// ─────────────────────────────────────────────────────────────
// MODAL: Ver Pedido (detalle con productos, Stitch inline — sin página Filament)
// ─────────────────────────────────────────────────────────────

it('ver un pedido abre el modal con TODOS sus productos (incluye bebidas)', function () {
    $mozo = makePedidosUser('Mozo');

    $comidaCat = Category::firstOrCreate(['name' => 'Comida']);
    $bebidasCat = Category::firstOrCreate(['name' => 'Bebidas']);

    $productoComida = Product::factory()->forRestaurant(1)->available()->create([
        'category_id' => $comidaCat->id,
        'price' => 100,
    ]);
    $productoBebida = Product::factory()->forRestaurant(1)->available()->create([
        'category_id' => $bebidasCat->id,
        'price' => 50,
    ]);

    $pedido = Order::factory()->forRestaurant(1)->pending()->create([
        'type' => 'para_llevar',
        'table_id' => null,
        'waiter_id' => $mozo->id,
        'customer_name' => 'Ana',
    ]);
    $pedido->orderProducts()->delete(); // descartar ítems aleatorios del factory
    $pedido->orderProducts()->create(['product_id' => $productoComida->id, 'quantity' => 1, 'price' => 100]);
    $pedido->orderProducts()->create(['product_id' => $productoBebida->id, 'quantity' => 3, 'price' => 50]);

    $component = Livewire::actingAs($mozo)->test(GestionPedidos::class);
    $component->call('verPedido', $pedido->id);

    $detalle = $component->instance()->detallePedido;

    // El modal muestra TODOS los productos (2 ítems, incluye la bebida).
    expect($detalle['items'])->toHaveCount(2)
        ->and(collect($detalle['items'])->pluck('name'))->toContain($productoComida->name)
        ->and(collect($detalle['items'])->pluck('name'))->toContain($productoBebida->name)
        ->and($detalle['total'])->toBe(250.0);
});
