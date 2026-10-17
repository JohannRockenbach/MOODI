<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Caja;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * TAREA 1 (P0 — seguridad): la acción de tabla `registerPayment` ("Cobrar")
 * de OrderResource debe estar restringida a super_admin, Cajero y Mozo
 * (decisión del dueño 2026-09-17; Cocinero NO cobra).
 *
 * Adaptación documentada: el happy path usa MOZO y no CAJERO. Motivo:
 * OrderPolicy::viewAny() permite super_admin/Mozo/Cocinero y EXCLUYE a
 * Cajero ("Cajero no opera pedidos"): un Cajero ni siquiera puede montar la
 * página ListOrders (abort 403 en el mount), por lo que no es posible
 * (ni necesario) probar el cobro de un Cajero a través del listado.
 * La defensa de la acción (abort_unless con hasAnyRole) igualmente habilita
 * a Cajero, consistente con HasCobroRapido, pero la página lo bloquea antes.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function ordertestUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function ordertestOpenCaja(User $user): Caja
{
    return Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $user->id,
        'restaurant_id' => 1,
    ]);
}

function ordertestPendingOrder(User $waiter, float $price = 500.0, int $qty = 2): Order
{
    $product = Product::factory()->create([
        'name' => 'Producto Test '.uniqid(),
        'price' => $price,
        'is_available' => true,
        'category_id' => \App\Models\Category::firstOrCreate(['name' => 'Test'])->id,
        'restaurant_id' => 1,
        'recipe_id' => null,
    ]);

    $order = Order::create([
        'restaurant_id' => 1,
        'table_id' => null,
        'waiter_id' => $waiter->id,
        'status' => 'pending',
        'type' => 'para_llevar',
        'stock_deducted' => false,
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'price' => $price,
    ]);

    return $order;
}

// ─────────────────────────────────────────────────────────────
// 1) HAPPY PATH — Mozo (decisión del dueño) registra el pago de un
//    pedido pending sin venta desde el listado de pedidos.
// ─────────────────────────────────────────────────────────────

it('permite a un Mozo registrar el pago de un pedido pending sin venta desde el listado', function () {
    $mozo = ordertestUser('Mozo');
    ordertestOpenCaja($mozo);
    $order = ordertestPendingOrder($mozo, price: 500.0, qty: 2); // total 1000

    Livewire::actingAs($mozo)
        ->test(ListOrders::class)
        ->callTableAction('registerPayment', $order, ['payment_method' => 'cash'])
        ->assertHasNoTableActionErrors();

    $sale = Sale::where('order_id', $order->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->status)->toBe('paid')
        ->and($sale->payment_method)->toBe('cash')
        ->and((float) $sale->total_amount)->toBe(1000.0)
        ->and($sale->caja_id)->not->toBeNull()
        ->and($order->fresh()->sale()->exists())->toBeTrue();
});

// ─────────────────────────────────────────────────────────────
// 2) SEGURIDAD — Cocinero NO puede cobrar (403 en el listado + 403 en la acción)
// ─────────────────────────────────────────────────────────────

it('un Cocinero no puede montar el listado de pedidos (403) ni ejecutar la acción Cobrar', function () {
    $cocinero = ordertestUser('Cocinero');
    ordertestOpenCaja($cocinero);
    $order = ordertestPendingOrder($cocinero, price: 500.0, qty: 1);

    // (a) TAREA 1: OrderPolicy::viewAny() excluye al Cocinero → el listado
    //     de pedidos aborta 403 al montar (no ve la lista ni la acción).
    Livewire::actingAs($cocinero)
        ->test(ListOrders::class)
        ->assertStatus(403);

    // (b) Defensa en profundidad: el callback de la acción registerPayment
    //     aborta con 403 si se invoca directo con un Cocinero (bypass de la
    //     UI), consistente con HasCobroRapido::cobrar(). Se monta el listado
    //     con un Mozo (que sí accede) y se llama al callback con el Cocinero
    //     autenticado.
    $mozo = ordertestUser('Mozo');
    $component = Livewire::actingAs($mozo)->test(ListOrders::class);

    expect($component->instance()->getTable()->getAction('registerPayment'))->not->toBeNull();

    $action = $component->instance()->getTable()->getAction('registerPayment');
    $action->record($order);

    $this->actingAs($cocinero);

    $this->expectException(HttpException::class);

    $action->call(['record' => $order, 'data' => ['payment_method' => 'cash']]);
});

// ─────────────────────────────────────────────────────────────
// 3) SEGURIDAD — super_admin puede cobrar (acción visible + venta creada)
// ─────────────────────────────────────────────────────────────

it('permite a super_admin registrar el pago desde el listado', function () {
    $admin = ordertestUser('super_admin');
    ordertestOpenCaja($admin);
    $order = ordertestPendingOrder($admin, price: 1000.0, qty: 1);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->assertTableActionVisible('registerPayment', $order)
        ->callTableAction('registerPayment', $order, ['payment_method' => 'card'])
        ->assertHasNoTableActionErrors();

    $sale = Sale::where('order_id', $order->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->status)->toBe('paid')
        ->and($sale->payment_method)->toBe('card')
        ->and((float) $sale->total_amount)->toBe(1000.0);
});

// ─────────────────────────────────────────────────────────────
// 4) EDGE CASE — pedido que ya tiene venta no permite cobrar de nuevo
// ─────────────────────────────────────────────────────────────

it('no permite registrar un segundo pago para un pedido que ya tiene venta', function () {
    $mozo = ordertestUser('Mozo');
    $caja = ordertestOpenCaja($mozo);
    $order = ordertestPendingOrder($mozo, price: 500.0, qty: 2);

    Sale::create([
        'order_id' => $order->id,
        'restaurant_id' => 1,
        'caja_id' => $caja->id,
        'total_amount' => 1000.0,
        'payment_method' => 'cash',
        'status' => 'paid',
    ]);

    // (a) La acción queda oculta: ya existe una venta para el pedido.
    Livewire::actingAs($mozo)
        ->test(ListOrders::class)
        ->assertTableActionHidden('registerPayment', $order);

    // (b) Defensa en profundidad: forzar el callback de la acción directo no
    //     crea una segunda venta — la unicidad de sales.order_id lo impide
    //     (el action() captura el error en la transacción y notifica).
    $component = Livewire::actingAs($mozo)->test(ListOrders::class);
    $action = $component->instance()->getTable()->getAction('registerPayment');
    $action->record($order);
    $action->call(['record' => $order, 'data' => ['payment_method' => 'card']]);

    expect(Sale::count())->toBe(1)
        ->and(Sale::first()->payment_method)->toBe('cash');
});
