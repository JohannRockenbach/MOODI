<?php

namespace Tests\Feature;

use App\Filament\Pages\CobrarCuenta;
use App\Models\Caja;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Restaurant;
use App\Models\Sale;
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

function makeCobroUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function makeOpenCaja(User $user): Caja
{
    return Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $user->id,
        'restaurant_id' => 1,
    ]);
}

function makeCobroTable(array $overrides = []): Table
{
    return Table::factory()->create(array_merge([
        'number' => 55,
        'capacity' => 4,
        'location' => 'Salón',
        'status' => Table::STATUS_OCCUPIED,
        'restaurant_id' => 1,
    ], $overrides));
}

function makeDirectPosProduct(string $name, float $price, float $stock = 50): Product
{
    $category = Category::firstOrCreate(['name' => 'Bebidas']);

    return Product::factory()->create([
        'name' => $name,
        'price' => $price,
        'is_available' => true,
        'category_id' => $category->id,
        'restaurant_id' => 1,
        'recipe_id' => null,
        'stock' => $stock,
    ]);
}

/**
 * Producto elaborado con receta: 1 ingrediente con lotes FEFO que requieren
 * `requiredAmount` unidades por ítem.
 */
function makeRecipePosProduct(string $name, float $price, float $batchQuantity = 10, float $requiredAmount = 2): Product
{
    $ingredient = Ingredient::create([
        'name' => 'Ingrediente '.uniqid(),
        'measurement_unit' => 'unidades',
        'reorder_point' => 0,
        'min_stock' => 0,
        'restaurant_id' => 1,
    ]);

    IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => $batchQuantity,
        'expiration_date' => now()->addDays(5),
    ]);

    $recipe = Recipe::create(['name' => 'Receta '.uniqid(), 'instructions' => 'Preparar']);
    $recipe->ingredients()->attach($ingredient->id, ['required_amount' => $requiredAmount]);

    $category = Category::firstOrCreate(['name' => 'Hamburguesas']);

    return Product::factory()->create([
        'name' => $name,
        'price' => $price,
        'is_available' => true,
        'category_id' => $category->id,
        'restaurant_id' => 1,
        'recipe_id' => $recipe->id,
        'stock' => 0,
    ]);
}

function makeOrderOnTable(Table $table, User $waiter, Product $product, int $qty, float $price, string $status = 'pending'): Order
{
    $order = Order::create([
        'restaurant_id' => 1,
        'table_id' => $table->id,
        'waiter_id' => $waiter->id,
        'status' => $status,
        'type' => 'salon',
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'price' => $price,
    ]);

    return $order;
}

function makePercentDiscount(float $value = 10.0): Discount
{
    return Discount::create([
        'name' => 'Descuento '.$value.'%',
        'code' => 'DSC'.uniqid(),
        'type' => 'percentage',
        'value' => $value,
        'is_active' => true,
        'restaurant_id' => 1,
    ]);
}

// ─────────────────────────────────────────────────────────────
// HAPPY PATH
// ─────────────────────────────────────────────────────────────

it('cobra una mesa con 2 comandas: crea 2 ventas paid, completa pedidos, libera mesa y descuenta stock FEFO una sola vez', function () {
    $mozo = makeCobroUser('Mozo');
    $caja = makeOpenCaja($mozo);
    $table = makeCobroTable(['number' => 5]);

    // Comanda A: hamburguesa con receta (FEFO por lote de ingrediente).
    $burger = makeRecipePosProduct('Burger Clásica', 1000.0, batchQuantity: 10, requiredAmount: 2);
    $orderA = makeOrderOnTable($table, $mozo, $burger, 2, 1000.0); // 2000

    // Comanda B: bebida sin receta (stock directo).
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0, stock: 50);
    $orderB = makeOrderOnTable($table, $mozo, $coca, 3, 500.0); // 1500

    $component = Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CobrarCuenta::class)
        ->assertSet('selectedTableId', $table->id);

    expect($component->instance()->account['orders_count'])->toBe(2)
        ->and($component->instance()->account['items_count'])->toBe(5)
        ->and($component->instance()->subtotal)->toBe(3500.0);

    $component->set('paymentMethod', 'card')->call('cobrarCuenta')->assertHasNoErrors();

    // 2 ventas paid con método de pago, caja, cajero y restaurante correctos.
    $saleA = Sale::where('order_id', $orderA->id)->first();
    $saleB = Sale::where('order_id', $orderB->id)->first();

    expect($saleA)->not->toBeNull()
        ->and($saleB)->not->toBeNull()
        ->and($saleA->status)->toBe('paid')
        ->and($saleB->status)->toBe('paid')
        ->and($saleA->payment_method)->toBe('card')
        ->and($saleB->payment_method)->toBe('card')
        ->and($saleA->restaurant_id)->toBe(1)
        ->and($saleB->restaurant_id)->toBe(1)
        ->and($saleA->caja_id)->toBe($caja->id)
        ->and($saleB->caja_id)->toBe($caja->id)
        ->and($saleA->cashier_id)->toBe($mozo->id)
        ->and($saleB->cashier_id)->toBe($mozo->id)
        ->and((float) $saleA->total_amount)->toBe(2000.0)
        ->and((float) $saleB->total_amount)->toBe(1500.0);

    // Pedidos completados y stock descontado UNA sola vez (marcado).
    expect($orderA->fresh()->status)->toBe('completed')
        ->and($orderB->fresh()->status)->toBe('completed')
        ->and($orderA->fresh()->stock_deducted)->toBeTrue()
        ->and($orderB->fresh()->stock_deducted)->toBeTrue();

    // FEFO: lote del ingrediente 10 - (2 unidades × 2 por receta) = 6.
    $batch = $burger->recipe->ingredients->first()->batches()->first();
    expect((float) $batch->fresh()->quantity)->toBe(6.0)
        // Stock directo: 50 - 3 = 47.
        ->and((int) $coca->fresh()->stock)->toBe(47);

    // La mesa quedó liberada (máquina de estados).
    expect($table->fresh()->status)->toBe(Table::STATUS_AVAILABLE);
});

// ─────────────────────────────────────────────────────────────
// SEGURIDAD / AUTORIZACIÓN
// ─────────────────────────────────────────────────────────────

it('permite el acceso solo a super_admin, Cajero y Mozo (Cocinero NO)', function () {
    foreach (['super_admin', 'Cajero', 'Mozo'] as $role) {
        auth()->login(makeCobroUser($role));
        expect(CobrarCuenta::canAccess())->toBeTrue("{$role} debería poder acceder al TPV de cobro");
    }

    auth()->login(makeCobroUser('Cocinero'));
    expect(CobrarCuenta::canAccess())->toBeFalse();
});

it('deniega con 403 la página y el montaje Livewire para Cocinero, sin ventas', function () {
    $cocinero = makeCobroUser('Cocinero');
    $table = makeCobroTable();
    makeOpenCaja($cocinero); // aunque exista caja abierta, no puede cobrar

    $this->actingAs($cocinero)
        ->get(CobrarCuenta::getUrl())
        ->assertForbidden();

    Livewire::actingAs($cocinero)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CobrarCuenta::class)
        ->assertStatus(403);

    expect(Sale::count())->toBe(0)
        ->and($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

it('un Mozo registra la venta con cashier_id propio (rol habilitado por decisión del dueño)', function () {
    // Cubre la permisología explícita del TPV: Mozo cobra (aunque SalePolicy
    // restringe el recurso Sale al Cajero, este panel es un flujo aparte
    // autorizado por el dueño 2026-09-17).
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable();
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0);
    $order = makeOrderOnTable($table, $mozo, $coca, 2, 500.0);

    Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CobrarCuenta::class)
        ->set('paymentMethod', 'card')
        ->call('cobrarCuenta')
        ->assertHasNoErrors();

    $sale = Sale::where('order_id', $order->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->cashier_id)->toBe($mozo->id)
        ->and((float) $sale->total_amount)->toBe(1000.0);
});

// ─────────────────────────────────────────────────────────────
// EDGE CASES
// ─────────────────────────────────────────────────────────────

it('no cobra sin caja abierta: error y sin ventas', function () {
    $mozo = makeCobroUser('Mozo');
    $table = makeCobroTable();
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0);
    $order = makeOrderOnTable($table, $mozo, $coca, 2, 500.0);
    // Sin caja abierta (ninguna creada).

    Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CobrarCuenta::class)
        ->set('paymentMethod', 'card')
        ->call('cobrarCuenta')
        ->assertHasNoErrors(); // el error viaja como Notification, no como validación

    expect(Sale::count())->toBe(0)
        ->and($order->fresh()->status)->toBe('pending')
        ->and($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

it('no cobra una mesa sin pedidos activos: error y sin ventas', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable();

    // Pedido ya completado: NO es una cuenta activa.
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0);
    $completed = makeOrderOnTable($table, $mozo, $coca, 2, 500.0, status: 'completed');

    Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CobrarCuenta::class)
        ->set('paymentMethod', 'card')
        ->call('cobrarCuenta')
        ->assertHasNoErrors();

    expect(Sale::count())->toBe(0)
        ->and($completed->fresh()->status)->toBe('completed')
        ->and($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

it('calcula el vuelto en vivo y cobra en efectivo cuando el recibido cubre el total', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable();
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 1000.0);
    $order = makeOrderOnTable($table, $mozo, $coca, 2, 1000.0); // total 2000

    $component = Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CobrarCuenta::class);

    expect($component->instance()->total)->toBe(2000.0)
        ->and($component->instance()->canCobrar())->toBeFalse(); // sin monto recibido

    $component->set('montoRecibido', '2500');

    expect($component->instance()->vuelto)->toBe(500.0)
        ->and($component->instance()->canCobrar())->toBeTrue()
        ->and($component->instance()->cashInsufficient())->toBeFalse();

    $component->call('cobrarCuenta')->assertHasNoErrors();

    $sale = Sale::where('order_id', $order->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->payment_method)->toBe('cash')
        ->and((float) $sale->total_amount)->toBe(2000.0)
        ->and($order->fresh()->status)->toBe('completed');
});

it('bloquea el cobro en efectivo cuando el monto recibido es menor al total', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable();
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 1000.0);
    $order = makeOrderOnTable($table, $mozo, $coca, 2, 1000.0); // total 2000

    $component = Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CobrarCuenta::class)
        ->set('montoRecibido', '1500');

    expect($component->instance()->vuelto)->toBe(0.0)
        ->and($component->instance()->canCobrar())->toBeFalse()
        ->and($component->instance()->cashInsufficient())->toBeTrue();

    $component->call('cobrarCuenta')->assertHasNoErrors();

    expect(Sale::count())->toBe(0)
        ->and($order->fresh()->status)->toBe('pending')
        ->and($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

it('no descuenta stock dos veces cuando el pedido ya fue descontado (idempotencia)', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable();
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0, stock: 50);

    // Pedido que ya pasó por 'processing': stock ya descontado (marcado).
    $order = makeOrderOnTable($table, $mozo, $coca, 3, 500.0);
    $order->stock_deducted = true;
    $order->saveQuietly();

    Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CobrarCuenta::class)
        ->set('paymentMethod', 'card')
        ->call('cobrarCuenta')
        ->assertHasNoErrors();

    // El stock NO se vuelve a tocar: sigue en 50.
    expect((int) $coca->fresh()->stock)->toBe(50)
        ->and($order->fresh()->stock_deducted)->toBeTrue()
        ->and($order->fresh()->status)->toBe('completed')
        ->and(Sale::count())->toBe(1);
});

it('aplica un descuento porcentual y lo reparte proporcional entre las 2 ventas', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable();

    $platoA = makeDirectPosProduct('Plato A', 1000.0, stock: 30);
    $platoB = makeDirectPosProduct('Plato B', 2000.0, stock: 30);
    $orderA = makeOrderOnTable($table, $mozo, $platoA, 1, 1000.0); // 1000
    $orderB = makeOrderOnTable($table, $mozo, $platoB, 1, 2000.0); // 2000
    makePercentDiscount(10.0); // 10% → 300 sobre subtotal 3000

    $component = Livewire::actingAs($mozo)
        ->withQueryParams(['table_id' => $table->id])
        ->test(CobrarCuenta::class);

    expect($component->instance()->subtotal)->toBe(3000.0);

    $discount = Discount::where('restaurant_id', 1)->first();

    $component->set('selectedDiscounts', [$discount->id]);

    expect($component->instance()->discountAmount)->toBe(300.0)
        ->and($component->instance()->total)->toBe(2700.0);

    $component->set('paymentMethod', 'card')
        ->call('cobrarCuenta')
        ->assertHasNoErrors();

    $saleA = Sale::where('order_id', $orderA->id)->first();
    $saleB = Sale::where('order_id', $orderB->id)->first();

    $pivotA = $saleA->discounts()->where('discounts.id', $discount->id)->first()->pivot->amount_discounted;
    $pivotB = $saleB->discounts()->where('discounts.id', $discount->id)->first()->pivot->amount_discounted;

    // Ventas con descuento proporcional: 1000→900 y 2000→1800.
    expect((float) $saleA->total_amount)->toBe(900.0)
        ->and((float) $saleB->total_amount)->toBe(1800.0)
        ->and((float) $pivotA)->toBe(100.0)
        ->and((float) $pivotB)->toBe(200.0);

    // Consistencia: suma de ventas = subtotal − descuento (dentro de ±0.01).
    $sum = (float) $saleA->fresh()->total_amount + (float) $saleB->fresh()->total_amount;

    expect($sum)->toBeGreaterThanOrEqual(2699.99)
        ->and($sum)->toBeLessThanOrEqual(2700.01);
});