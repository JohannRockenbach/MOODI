<?php

namespace Tests\Feature;

use App\Filament\Pages\CrearPedido;
use App\Filament\Pages\TableMap;
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

function makeTakeawayOrder(User $waiter, Product $product, int $qty, float $price, string $status = 'pending'): Order
{
    $order = Order::create([
        'restaurant_id' => 1,
        'table_id' => null,
        'waiter_id' => $waiter->id,
        'status' => $status,
        'type' => 'para_llevar',
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'price' => $price,
    ]);

    return $order;
}

/**
 * Ítem del carrito de CrearPedido (draft del TPV): misma estructura que la
 * propiedad pública $items.
 */
function makeDraftItem(Product $product, int $qty, string $note = ''): array
{
    return [
        'product_id' => $product->id,
        'name' => $product->name,
        'price' => (float) $product->price,
        'qty' => $qty,
        'note' => $note,
        'section_key' => 'hamburguesas',
        'section_label' => '🍔 Hamburguesas',
    ];
}

// ─────────────────────────────────────────────────────────────
// 1) HAPPY PATH — MESA con comanda enviada + draft (BUG DEL DUEÑO)
//    El draft cobrado queda PENDING (cocina), la venta SIEMPRE se registra,
//    sale_payments suman cada total, la mesa se libera y stock se descuenta
//    UNA vez.
// ─────────────────────────────────────────────────────────────

it('cobra mesa con comanda enviada + draft (bug del dueño): draft pending a cocina, 2 Sales paid, sale_payments, mesa liberada, stock una vez y carrito limpio', function () {
    $mozo = makeCobroUser('Mozo');
    $caja = makeOpenCaja($mozo);
    $table = makeCobroTable(['number' => 5]);

    // Comanda enviada: burger con receta (FEFO por lote de ingrediente).
    $burger = makeRecipePosProduct('Burger Clásica', 1000.0);
    $orderSent = makeOrderOnTable($table, $mozo, $burger, 2, 1000.0); // 2000

    // Draft (carrito del TPV): 1 burger + 1 coca → 1500.
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0, stock: 50);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);
    $component->set('selectedTableId', $table->id)
        ->set('kitchenNote', 'Bien cocidas')
        ->call('addItem', $burger->id)
        ->call('addItem', $coca->id)
        ->call('abrirCobro');

    $account = $component->instance()->account;

    expect($component->get('open'))->toBeTrue()
        ->and($component->get('cobroMode'))->toBe('nuevo') // draft presente → pestaña Nuevo
        ->and($account['has_draft'])->toBeTrue()
        ->and($account['orders_count'])->toBe(2)
        ->and($account['items_count'])->toBe(4) // draft 2 + enviada 2
        ->and($component->instance()->accountSubtotal())->toBe(3500.0)
        ->and($component->get('payments'))->toBe([['method' => 'cash', 'amount' => '3500']]);

    // Sin SPLIT: una fila única card con el total → comportamiento idéntico al anterior.
    $component->set('payments.0.method', 'card')->call('cobrar')->assertHasNoErrors();

    // (a) El draft se creó como Order PENDING (visible en cocina: ordenes abiertas);
    //     la comanda ENVIADA sí pasa a completed.
    $draftOrder = Order::where('restaurant_id', 1)->where('status', 'pending')->where('id', '!=', $orderSent->id)->first();

    expect($draftOrder)->not->toBeNull()
        ->and(in_array($draftOrder->status, Table::ORDER_OPEN_STATUSES, true))->toBeTrue()
        ->and($draftOrder->type)->toBe('salon')
        ->and($draftOrder->waiter_id)->toBe($mozo->id)
        ->and($draftOrder->stock_deducted)->toBeTrue()
        ->and($draftOrder->notes)->toBe('Bien cocidas')
        ->and((int) $draftOrder->orderProducts()->where('product_id', $coca->id)->first()->price)->toBe(500)
        ->and($orderSent->fresh()->status)->toBe('completed');

    // (b) Sale paid para AMBOS pedidos (la venta SIEMPRE se registra).
    $saleSent = Sale::where('order_id', $orderSent->id)->first();
    $saleDraft = Sale::where('order_id', $draftOrder->id)->first();

    expect(Sale::count())->toBe(2)
        ->and($saleSent->status)->toBe('paid')
        ->and($saleDraft->status)->toBe('paid')
        ->and($saleSent->payment_method)->toBe('card')
        ->and($saleDraft->payment_method)->toBe('card')
        ->and($saleDraft->caja_id)->toBe($caja->id)
        ->and($saleDraft->cashier_id)->toBe($mozo->id)
        ->and((float) $saleSent->total_amount)->toBe(2000.0)
        ->and((float) $saleDraft->total_amount)->toBe(1500.0);

    // (c) sale_payments creados: una fila por Sale que suma su total exacto.
    expect($saleSent->payments->count())->toBe(1)
        ->and((float) $saleSent->payments->first()->amount)->toBe(2000.0)
        ->and($saleDraft->payments->count())->toBe(1)
        ->and((float) $saleDraft->payments->first()->amount)->toBe(1500.0);

    // (d) La mesa quedó liberada (el cliente pagó; la cocina prepara el draft).
    expect($table->fresh()->status)->toBe(Table::STATUS_AVAILABLE);

    // (e) Stock FEFO descontado UNA vez por order: enviada 2×2 + draft 1×2 = 6 → 10 − 6 = 4.
    $batch = $burger->recipe->ingredients->first()->batches()->first();
    expect((float) $batch->fresh()->quantity)->toBe(4.0)
        ->and((int) $coca->fresh()->stock)->toBe(49);

    // (f) El carrito se limpia al llegar 'comanda-cobrada' a la página.
    $component->dispatch('comanda-cobrada');

    expect($component->get('items'))->toBe([]);
});

// ─────────────────────────────────────────────────────────────
// 1b) MESA LIBRE (available) — regresión MESA FANTASMA
//     Cobro directo de un draft de SALÓN sobre una mesa que estaba
//     available: occupy() durante el cobro + refresh()/release() →
//     la mesa termina available de nuevo. Sin el refresh(), release()
//     ve el status STALE 'available' de la instancia pre-cargada y NO
//     libera (exige status === occupied) → mesa fantasma: 'occupied'
//     en DB sin comandas activas.
// ─────────────────────────────────────────────────────────────

it('cobra un draft de salón en una mesa LIBRE (available): la mesa termina available (liberada) y el draft queda pending cobrado con Sale', function () {
    $mozo = makeCobroUser('Mozo');
    $caja = makeOpenCaja($mozo);

    // Mesa LIBRE: los demás tests de mesa crean la mesa OCCUPIED.
    $table = makeCobroTable(['number' => 77, 'status' => Table::STATUS_AVAILABLE]);

    $coca = makeDirectPosProduct('Coca Mesa Libre', 500.0, stock: 20);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);
    $component->set('selectedTableId', $table->id)
        ->set('items', [makeDraftItem($coca, 2)])
        ->call('abrirCobro');

    expect($component->get('open'))->toBeTrue()
        ->and($component->instance()->account['has_draft'])->toBeTrue()
        ->and($component->instance()->account['table_id'])->toBe($table->id)
        ->and($component->instance()->accountSubtotal())->toBe(1000.0)
        ->and($component->get('payments'))->toBe([['method' => 'cash', 'amount' => '1000']]);

    $component->set('payments.0.method', 'card')->call('cobrar')->assertHasNoErrors();

    // Draft de salón cobrado directo: queda PENDING (cocina), con Sale paid y
    // DESVINCULADO de la mesa (table_id → null) para que release() no lo
    // cuente como pedido activo que mantiene ocupada la mesa.
    $draftOrder = Order::where('restaurant_id', 1)->where('status', 'pending')->first();

    expect($draftOrder)->not->toBeNull()
        ->and($draftOrder->type)->toBe('salon')
        ->and($draftOrder->table_id)->toBeNull()
        ->and($draftOrder->stock_deducted)->toBeTrue();

    $sale = Sale::where('order_id', $draftOrder->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->status)->toBe('paid')
        ->and($sale->caja_id)->toBe($caja->id)
        ->and((float) $sale->total_amount)->toBe(1000.0)
        ->and((float) $sale->payments->sum('amount'))->toBe(1000.0);

    // POST-ESTADO: la mesa NUNCA queda 'occupied' sin comandas activas.
    expect($table->fresh()->status)->toBe(Table::STATUS_AVAILABLE)
        ->and($table->fresh()->hasActiveOrders())->toBeFalse();

    // Stock FEFO descontado UNA vez en el cobro: 20 − 2 = 18.
    expect((int) $coca->fresh()->stock)->toBe(18)
        ->and(Sale::count())->toBe(1);
});

// ─────────────────────────────────────────────────────────────
// 2) HAPPY PATH — Takeaway con draft (sin mesa)
// ─────────────────────────────────────────────────────────────

it('cobra un draft para llevar SIN mesa: Order pending para_llevar sin table_id, Sale paid y sin release', function () {
    $mozo = makeCobroUser('Mozo');
    $caja = makeOpenCaja($mozo);

    // Mesa ocupada con cuenta pendiente: NO debe cambiar de estado.
    $table = makeCobroTable(['number' => 7]);
    $plato = makeDirectPosProduct('Plato de Mesa', 500.0);
    makeOrderOnTable($table, $mozo, $plato, 1, 500.0);

    $burger = makeRecipePosProduct('Burger Takeaway', 1000.0);
    $coca = makeDirectPosProduct('Coca Takeaway', 500.0, stock: 50);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class)
        ->call('setOrderType', CrearPedido::TYPE_TAKEAWAY)
        ->call('addItem', $burger->id)
        ->call('addItem', $burger->id)
        ->call('addItem', $coca->id)
        ->call('abrirCobro');

    expect($component->get('open'))->toBeTrue()
        ->and($component->instance()->account['is_takeaway'])->toBeTrue()
        ->and($component->instance()->account['display_label'])->toBe('Comanda en curso')
        ->and($component->instance()->account['has_draft'])->toBeTrue()
        ->and($component->instance()->accountSubtotal())->toBe(2500.0);

    $component->set('payments.0.method', 'card')->call('cobrar')->assertHasNoErrors();

    $draftOrder = Order::where('type', 'para_llevar')->where('status', 'pending')->first();

    expect($draftOrder)->not->toBeNull()
        ->and(in_array($draftOrder->status, Table::ORDER_OPEN_STATUSES, true))->toBeTrue() // visible en cocina
        ->and($draftOrder->table_id)->toBeNull()
        ->and($draftOrder->waiter_id)->toBe($mozo->id)
        ->and($draftOrder->stock_deducted)->toBeTrue();

    $sale = Sale::where('order_id', $draftOrder->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->status)->toBe('paid')
        ->and($sale->payment_method)->toBe('card')
        ->and($sale->caja_id)->toBe($caja->id)
        ->and((float) $sale->total_amount)->toBe(2500.0)
        ->and((float) $sale->payments->sum('amount'))->toBe(2500.0);

    // Stock FEFO: burger 2×2=4 → 10 − 4 = 6; coca 50 − 1 = 49.
    $batch = $burger->recipe->ingredients->first()->batches()->first();
    expect((float) $batch->fresh()->quantity)->toBe(6.0)
        ->and((int) $coca->fresh()->stock)->toBe(49)
        ->and(Sale::count())->toBe(1)
        ->and($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED); // sin release
});

// ─────────────────────────────────────────────────────────────
// 3) HAPPY PATH — Takeaway YA creado (enviado a cocina) sin venta
// ─────────────────────────────────────────────────────────────

it('cobra un pedido para llevar existente (selectAccountOrder): Sale paid, completed y sin release', function () {
    $mozo = makeCobroUser('Mozo');
    $caja = makeOpenCaja($mozo);
    $burger = makeRecipePosProduct('Burger Takeaway', 1000.0);
    $order = makeTakeawayOrder($mozo, $burger, 2, 1000.0); // 2000

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class)->call('abrirCobro');

    // Sin contexto → selector interno; elegir el pedido para llevar.
    expect($component->instance()->account)->toBeNull();

    $component->call('selectAccountOrder', $order->id);

    expect($component->instance()->account['order_id'])->toBe($order->id)
        ->and($component->instance()->account['display_label'])->toBe('Pedido #'.$order->id)
        ->and($component->instance()->account['has_draft'])->toBeFalse()
        ->and($component->instance()->accountSubtotal())->toBe(2000.0);

    $component->set('payments.0.method', 'card')->call('cobrar')->assertHasNoErrors();

    $sale = Sale::where('order_id', $order->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->status)->toBe('paid')
        ->and($sale->caja_id)->toBe($caja->id)
        ->and((float) $sale->total_amount)->toBe(2000.0)
        ->and((float) $sale->payments->sum('amount'))->toBe(2000.0)
        ->and($order->fresh()->status)->toBe('completed') // ya fue a cocina
        ->and($order->fresh()->stock_deducted)->toBeTrue();

    // Sin mesa → sin release de ninguna mesa.
    expect(Table::where('status', Table::STATUS_AVAILABLE)->count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────
// 4) SPLIT DE PAGO — efectivo + tarjeta, reparto proporcional
// ─────────────────────────────────────────────────────────────

it('cobra con SPLIT efectivo + tarjeta: reparto proporcional por comanda, método dominante y vuelto de solo efectivo', function () {
    $mozo = makeCobroUser('Mozo');
    $caja = makeOpenCaja($mozo);
    $table = makeCobroTable(['number' => 12]);

    $platoA = makeDirectPosProduct('Plato A', 1000.0, stock: 30);
    $platoB = makeDirectPosProduct('Plato B', 2000.0, stock: 30);
    $orderA = makeOrderOnTable($table, $mozo, $platoA, 1, 1000.0); // 1000
    $orderB = makeOrderOnTable($table, $mozo, $platoB, 1, 2000.0); // 2000

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);
    $component->set('selectedTableId', $table->id)->call('abrirCobro');

    expect($component->instance()->accountSubtotal())->toBe(3000.0)
        ->and($component->instance()->paymentsTotal)->toBe(3000.0);

    // Split: $25 efectivo + $2975 tarjeta; recibido $200 → vuelto $175.
    $component->call('addPaymentRow')
        ->set('payments.0.method', 'cash')->set('payments.0.amount', '25')
        ->set('payments.1.method', 'card')->set('payments.1.amount', '2975')
        ->set('montoRecibido', '200');

    expect($component->instance()->paymentsTotal)->toBe(3000.0)
        ->and($component->instance()->vuelto)->toBe(175.0)
        ->and($component->instance()->canCobrar())->toBeTrue();

    $component->call('cobrar')->assertHasNoErrors();

    $saleA = Sale::where('order_id', $orderA->id)->first();
    $saleB = Sale::where('order_id', $orderB->id)->first();

    // Sale A (total 1000): cash 25 × 1000/3000 = 8.33 + card ajustada 991.67.
    expect($saleA->payment_method)->toBe('card') // dominante
        ->and((float) $saleA->payments->where('payment_method', 'cash')->sum('amount'))->toBe(8.33)
        ->and((float) $saleA->payments->where('payment_method', 'card')->sum('amount'))->toBe(991.67)
        ->and((float) $saleA->payments->sum('amount'))->toBe(1000.0);

    // Sale B (total 2000): cash 16.67 + card ajustada 1983.33 → dominante card.
    expect($saleB->payment_method)->toBe('card')
        ->and((float) $saleB->payments->where('payment_method', 'cash')->sum('amount'))->toBe(16.67)
        ->and((float) $saleB->payments->where('payment_method', 'card')->sum('amount'))->toBe(1983.33)
        ->and((float) $saleB->payments->sum('amount'))->toBe(2000.0);

    expect($saleA->caja_id)->toBe($caja->id)
        ->and($table->fresh()->status)->toBe(Table::STATUS_AVAILABLE);
});

// ─────────────────────────────────────────────────────────────
// 5) SEGURIDAD / AUTORIZACIÓN
// ─────────────────────────────────────────────────────────────

it('deniega el cobro a Cocinero (403 al montar las páginas) y habilita super_admin, Cajero y Mozo', function () {
    $cocinero = makeCobroUser('Cocinero');

    // Cocinero no monta NINGUNA página de cobro (canAccess → 403): el flujo
    // completo queda fuera (incluido cobrar() que además tiene defensa en
    // profundidad por rol dentro del trait).
    Livewire::actingAs($cocinero)->test(CrearPedido::class)->assertStatus(403);
    expect(Sale::count())->toBe(0);

    Livewire::actingAs($cocinero)->test(TableMap::class)->assertStatus(403);
    expect(Sale::count())->toBe(0);

    // super_admin por el TPV.
    $admin = makeCobroUser('super_admin');
    makeOpenCaja($admin);
    $tableAdmin = makeCobroTable(['number' => 80]);
    $cocaAdmin = makeDirectPosProduct('Coca Admin', 500.0);
    makeOrderOnTable($tableAdmin, $admin, $cocaAdmin, 1, 500.0);

    Livewire::actingAs($admin)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $tableAdmin->id)
        ->call('abrirCobro')
        ->set('payments.0.method', 'card')
        ->call('cobrar')
        ->assertHasNoErrors();

    // Cajero por el Mapa de Mesas (canAccess del TPV no incluye Cajero).
    $cajero = makeCobroUser('Cajero');
    makeOpenCaja($cajero);
    $tableCajero = makeCobroTable(['number' => 81]);
    $cocaCajero = makeDirectPosProduct('Coca Cajero', 500.0);
    makeOrderOnTable($tableCajero, $cajero, $cocaCajero, 1, 500.0);

    Livewire::actingAs($cajero)
        ->test(TableMap::class)
        ->call('selectTable', $tableCajero->id)
        ->call('abrirCobro', $tableCajero->id)
        ->set('payments.0.method', 'card')
        ->call('cobrar')
        ->assertHasNoErrors();

    expect(Sale::count())->toBe(2);
});

// ─────────────────────────────────────────────────────────────
// 6) F2 — solicitar-cobro abre el modal con el contexto real
// ─────────────────────────────────────────────────────────────

it('F2 (solicitar-cobro) abre el modal con la cuenta que incluye el draft del TPV', function () {
    $mozo = makeCobroUser('Mozo');
    $table = makeCobroTable(['number' => 9]);
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0, stock: 30);

    $component = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $table->id)
        ->call('addItem', $coca->id)
        ->dispatch('solicitar-cobro');

    expect($component->get('open'))->toBeTrue()
        ->and($component->get('draft'))->not->toBe([])
        ->and($component->instance()->account['has_draft'])->toBeTrue()
        ->and($component->instance()->account['table_id'])->toBe($table->id)
        ->and($component->instance()->accountSubtotal())->toBe(500.0);
});

// ─────────────────────────────────────────────────────────────
// 7) EDGE CASES
// ─────────────────────────────────────────────────────────────

it('no cobra sin caja abierta: error y sin ventas', function () {
    $mozo = makeCobroUser('Mozo');
    $table = makeCobroTable();
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0);
    makeOrderOnTable($table, $mozo, $coca, 2, 500.0);

    // Sin caja abierta (ninguna creada): error viaja como Notification.
    Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $table->id)
        ->call('abrirCobro')
        ->set('payments.0.method', 'card')
        ->call('cobrar')
        ->assertHasNoErrors();

    expect(Sale::count())->toBe(0)
        ->and($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

it('rechaza el draft con stock insuficiente (tamaño manipulado): no crea ventas ni Order', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0, stock: 2);

    // Manipulación del carrito: qty 5 con stock real 2.
    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);
    $component->set('orderType', CrearPedido::TYPE_TAKEAWAY);
    $component->set('items', [makeDraftItem($coca, 5)]);
    $component->call('abrirCobro')
        ->set('payments.0.method', 'card')
        ->call('cobrar')
        ->assertHasNoErrors();

    expect(Sale::count())->toBe(0)
        ->and(Order::where('type', 'para_llevar')->count())->toBe(0)
        ->and((int) $coca->fresh()->stock)->toBe(2);
});

it('no crea una segunda venta para un pedido ya vendido (carrera)', function () {
    $mozo = makeCobroUser('Mozo');
    $caja = makeOpenCaja($mozo);
    $coca = makeDirectPosProduct('Coca 500', 500.0);
    $order = makeTakeawayOrder($mozo, $coca, 2, 500.0); // 1000

    // Otro terminal cobró primero: la venta ya existe.
    Sale::create([
        'order_id' => $order->id,
        'restaurant_id' => 1,
        'caja_id' => $caja->id,
        'cashier_id' => $mozo->id,
        'total_amount' => 1000.0,
        'payment_method' => 'card',
        'status' => 'paid',
    ]);

    Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->call('abrirCobro')
        ->call('selectAccountOrder', $order->id)
        ->set('payments.0.method', 'card')
        ->call('cobrar')
        ->assertHasNoErrors(); // error viaja como Notification

    expect(Sale::count())->toBe(1)
        ->and($order->fresh()->status)->toBe('pending')
        ->and($order->fresh()->stock_deducted)->toBeFalse();
});

it('bloquea el cobro si el efectivo recibido es menor al efectivo del SPLIT', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable();
    $coca = makeDirectPosProduct('Coca 1000', 1000.0);
    makeOrderOnTable($table, $mozo, $coca, 2, 1000.0); // 2000

    $component = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $table->id)
        ->call('abrirCobro');

    $component->call('addPaymentRow')
        ->set('payments.0.method', 'cash')->set('payments.0.amount', '1500')
        ->set('payments.1.method', 'card')->set('payments.1.amount', '500')
        ->set('montoRecibido', '1000'); // efectivo requerido 1500 → insuficiente

    expect($component->instance()->canCobrar())->toBeFalse()
        ->and($component->instance()->cashInsufficient())->toBeTrue()
        ->and($component->instance()->vuelto)->toBe(0.0);

    $component->call('cobrar')->assertHasNoErrors();

    expect(Sale::count())->toBe(0)
        ->and($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

it('no vuelve a descontar stock en un pedido ya descontado (idempotencia)', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable();
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0, stock: 50);
    $order = makeOrderOnTable($table, $mozo, $coca, 3, 500.0);
    $order->stock_deducted = true;
    $order->saveQuietly();

    Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $table->id)
        ->call('abrirCobro')
        ->set('payments.0.method', 'card')
        ->call('cobrar')
        ->assertHasNoErrors();

    expect((int) $coca->fresh()->stock)->toBe(50)
        ->and($order->fresh()->stock_deducted)->toBeTrue()
        ->and($order->fresh()->status)->toBe('completed')
        ->and(Sale::count())->toBe(1);
});

it('aplica descuento DIRECTO al takeaway y PROPORCIONAL entre comandas de mesa (±0.01)', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);

    $discount = makePercentDiscount(10.0); // 10%

    // ———— Takeaway: descuento DIRECTO (subtotal == total del pedido único).
    $coca = makeDirectPosProduct('Coca 1000', 1000.0);
    $takeaway = makeTakeawayOrder($mozo, $coca, 2, 1000.0); // 2000

    $tw = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->call('abrirCobro')
        ->call('selectAccountOrder', $takeaway->id);

    $tw->call('toggleDiscount', $discount->id);

    expect($tw->instance()->discountAmount)->toBe(200.0)
        ->and($tw->instance()->accountTotal())->toBe(1800.0)
        ->and($tw->instance()->paymentsTotal)->toBe(1800.0); // fila única re-sincronizada

    // Fila única → método por tarjeta (sin efectivo, no requiere monto recibido).
    $tw->set('payments.0.method', 'card');

    expect($tw->instance()->canCobrar())->toBeTrue();

    $tw->call('cobrar')->assertHasNoErrors();

    $saleTakeaway = Sale::where('order_id', $takeaway->id)->first();

    expect((float) $saleTakeaway->total_amount)->toBe(1800.0)
        ->and((float) $saleTakeaway->payments->sum('amount'))->toBe(1800.0)
        ->and((float) $saleTakeaway->discounts()->first()->pivot->amount_discounted)->toBe(200.0);

    // ———— Mesa: descuento PROPORCIONAL entre las 2 comandas.
    $table = makeCobroTable(['number' => 13]);
    $platoA = makeDirectPosProduct('Plato A', 1000.0, stock: 30);
    $platoB = makeDirectPosProduct('Plato B', 2000.0, stock: 30);
    $orderA = makeOrderOnTable($table, $mozo, $platoA, 1, 1000.0); // 1000
    $orderB = makeOrderOnTable($table, $mozo, $platoB, 1, 2000.0); // 2000

    $mesa = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $table->id)
        ->call('abrirCobro');

    expect($mesa->instance()->accountSubtotal())->toBe(3000.0);

    $mesa->call('toggleDiscount', $discount->id);

    expect($mesa->instance()->discountAmount)->toBe(300.0)
        ->and($mesa->instance()->accountTotal())->toBe(2700.0)
        ->and($mesa->instance()->paymentsTotal)->toBe(2700.0);

    $mesa->set('payments.0.method', 'card');

    expect($mesa->instance()->canCobrar())->toBeTrue();

    $mesa->call('cobrar')->assertHasNoErrors();

    $saleA = Sale::where('order_id', $orderA->id)->first();
    $saleB = Sale::where('order_id', $orderB->id)->first();

    expect((float) $saleA->total_amount)->toBe(900.0)
        ->and((float) $saleB->total_amount)->toBe(1800.0)
        ->and((float) $saleA->discounts()->first()->pivot->amount_discounted)->toBe(100.0)
        ->and((float) $saleB->discounts()->first()->pivot->amount_discounted)->toBe(200.0);

    // Consistencia: suma de ventas = subtotal − descuento (dentro de ±0.01).
    $sum = (float) $saleA->fresh()->total_amount + (float) $saleB->fresh()->total_amount;

    expect($sum)->toBeGreaterThanOrEqual(2699.99)
        ->and($sum)->toBeLessThanOrEqual(2700.01);
});

it('cancelar el modal no cobra nada (sin ventas ni Order del draft)', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable();
    $coca = makeDirectPosProduct('Coca-Cola 350ml', 500.0);
    makeOrderOnTable($table, $mozo, $coca, 1, 500.0);

    $component = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $table->id)
        ->call('addItem', $coca->id)
        ->call('abrirCobro');

    expect($component->get('open'))->toBeTrue();

    $component->call('cancelar');

    expect($component->get('open'))->toBeFalse()
        ->and(Sale::count())->toBe(0)
        ->and(Order::count())->toBe(1) // solo la comanda enviada
        ->and($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

it('no cobra con SPLIT inválido (suma de métodos != total)', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable();
    $coca = makeDirectPosProduct('Coca 1000', 1000.0);
    makeOrderOnTable($table, $mozo, $coca, 2, 1000.0); // 2000

    $component = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $table->id)
        ->call('abrirCobro');

    $component->call('addPaymentRow')
        ->set('payments.0.method', 'cash')->set('payments.0.amount', '500')
        ->set('payments.1.method', 'card')->set('payments.1.amount', '500'); // suma 1000 != 2000

    expect($component->instance()->canCobrar())->toBeFalse()
        ->and(abs($component->instance()->paymentsTotal - $component->instance()->accountTotal()))->toBeGreaterThan(0.01);

    $component->call('cobrar')->assertHasNoErrors();

    expect(Sale::count())->toBe(0)
        ->and($table->fresh()->status)->toBe(Table::STATUS_OCCUPIED);
});

// ─────────────────────────────────────────────────────────────
// 8) MODOS DEL MODAL: NUEVO / EXISTENTES (decisión del dueño)
// ─────────────────────────────────────────────────────────────

// 8a) Modo inicial con draft → 'nuevo' y el cobro crea el Order del draft
//     (el happy path 1 ya lo cubre y ahora también valida cobroMode).

it('abrir sin contexto (F2 / botón COBRAR con TPV vacío) inicia en modos EXISTENTES con la lista disponible', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);

    // Dos cuentas pendientes reales: una mesa ocupada + un takeaway sin venta.
    $table = makeCobroTable(['number' => 91]);
    $coca = makeDirectPosProduct('Coca 500', 500.0);
    makeOrderOnTable($table, $mozo, $coca, 2, 500.0); // 1000
    $takeaway = makeTakeawayOrder($mozo, $coca, 1, 500.0); // 500

    $component = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->call('abrirCobro'); // sin mesa, sin pedido, sin draft

    expect($component->get('open'))->toBeTrue()
        ->and($component->get('cobroMode'))->toBe('existentes')
        ->and($component->instance()->account)->toBeNull() // la lista se muestra
        ->and($component->instance()->chargeableTables->pluck('id'))->toContain($table->id)
        ->and($component->instance()->takeawayOrders->pluck('id'))->toContain($takeaway->id);

    // F2 (solicitar-cobro) sin contexto: mismo modo inicial.
    $component->dispatch('solicitar-cobro');

    expect($component->get('cobroMode'))->toBe('existentes')
        ->and($component->instance()->account)->toBeNull();
});

// 8b) Los MODO EXISTENTES IGNORA el draft del payload: al cobrar una mesa con
//     comanda enviada NO se crea ningún Order nuevo del draft (solo la Sale de
//     la comanda enviada → completed) aunque el payload trajera el draft.

it('modo EXISTENTES ignora el draft del payload: cobra solo la comanda enviada y NO crea un Order nuevo', function () {
    $mozo = makeCobroUser('Mozo');
    $caja = makeOpenCaja($mozo);
    $table = makeCobroTable(['number' => 92]);
    $coca = makeDirectPosProduct('Coca 500', 500.0, stock: 50);
    $orderSent = makeOrderOnTable($table, $mozo, $coca, 2, 500.0); // 1000 enviada

    // El TPV tiene un draft EN EL CARRITO (viene en el payload al abrir).
    $component = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $table->id)
        ->call('addItem', $coca->id)
        ->call('abrirCobro');

    // Abre en modo 'nuevo' (hay draft); el mozo cambia a Existentes.
    expect($component->get('cobroMode'))->toBe('nuevo');

    $component->call('setCobroMode', 'existentes');

    // La cuenta IGNORA el draft: solo la comanda enviada de la mesa.
    expect($component->get('cobroMode'))->toBe('existentes')
        ->and($component->get('draft'))->not->toBe([]) // el draft sigue en el payload
        ->and($component->instance()->account['has_draft'])->toBeFalse()
        ->and($component->instance()->account['orders_count'])->toBe(1)
        ->and($component->instance()->account['items_count'])->toBe(2)
        ->and($component->instance()->accountSubtotal())->toBe(1000.0);

    $component->set('payments.0.method', 'card')->call('cobrar')->assertHasNoErrors();

    // NO se creó ningún Order del draft: solo existe la comanda enviada, cobrada.
    expect(Order::count())->toBe(1)
        ->and($orderSent->fresh()->status)->toBe('completed')
        ->and(Sale::count())->toBe(1)
        ->and((float) Sale::first()->total_amount)->toBe(1000.0)
        ->and(Sale::first()->caja_id)->toBe($caja->id)
        ->and($table->fresh()->status)->toBe(Table::STATUS_AVAILABLE);
});

// 8c) Alternar tabs no rompe la cuenta ni el estado de pago (computeds frescas).

it('alternar entre EXISTENTES y NUEVO mantiene la cuenta y el split frescos', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable(['number' => 93]);
    $coca = makeDirectPosProduct('Coca 500', 500.0, stock: 50);
    makeOrderOnTable($table, $mozo, $coca, 2, 500.0); // 1000 enviada

    $component = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $table->id)
        ->call('addItem', $coca->id) // draft 500
        ->call('abrirCobro');

    // Nuevo: cuenta = draft + enviada = 1500.
    expect($component->get('cobroMode'))->toBe('nuevo')
        ->and($component->instance()->accountSubtotal())->toBe(1500.0)
        ->and($component->get('payments'))->toBe([['method' => 'cash', 'amount' => '1500']]);

    // → Existentes: el draft sale de la cuenta (solo enviada = 1000), split re-sincronizado.
    $component->call('setCobroMode', 'existentes')
        ->set('payments.0.method', 'card'); // sin efectivo → canCobrar sin montoRecibido

    expect($component->get('cobroMode'))->toBe('existentes')
        ->and($component->instance()->accountSubtotal())->toBe(1000.0)
        ->and($component->get('payments'))->toBe([['method' => 'card', 'amount' => '1000']])
        ->and($component->instance()->canCobrar())->toBeTrue();

    // → Nuevo otra vez: el draft vuelve a la cuenta (1500), split fresco de nuevo
    //   (el método elegido se conserva, el monto se re-sincroniza).
    $component->call('setCobroMode', 'nuevo');

    expect($component->get('cobroMode'))->toBe('nuevo')
        ->and($component->instance()->accountSubtotal())->toBe(1500.0)
        ->and($component->get('payments'))->toBe([['method' => 'card', 'amount' => '1500']])
        ->and($component->instance()->canCobrar())->toBeTrue();

    // El cobro en modo 'nuevo' crea el Order del draft (la lógica no se rompió).
    $component->set('payments.0.method', 'card')->call('cobrar')->assertHasNoErrors();

    expect(Order::count())->toBe(2) // enviada + draft creado
        ->and(Sale::count())->toBe(2);
});

// 8d) Modo EXISTENTES con selección: al elegir cuenta el modo sigue en
//     'existentes' y el draft del payload NUNCA se cuelga de la cuenta.

it('seleccionar una cuenta existente mantiene el modo EXISTENTES y no arrastra el draft', function () {
    $mozo = makeCobroUser('Mozo');
    makeOpenCaja($mozo);
    $table = makeCobroTable(['number' => 94]);
    $coca = makeDirectPosProduct('Coca 500', 500.0, stock: 50);
    makeOrderOnTable($table, $mozo, $coca, 2, 500.0); // 1000

    $component = Livewire::actingAs($mozo)
        ->test(CrearPedido::class)
        ->set('selectedTableId', $table->id)
        ->call('addItem', $coca->id) // draft 500 en el carrito
        ->call('abrirCobro')
        ->call('setCobroMode', 'existentes')
        ->call('selectAccountTable', $table->id);

    expect($component->get('cobroMode'))->toBe('existentes')
        ->and($component->instance()->account['has_draft'])->toBeFalse()
        ->and($component->instance()->accountSubtotal())->toBe(1000.0);

    $component->set('payments.0.method', 'card')->call('cobrar')->assertHasNoErrors();

    expect(Order::count())->toBe(1) // la enviada: el draft NO se creó
        ->and(Sale::count())->toBe(1);
});