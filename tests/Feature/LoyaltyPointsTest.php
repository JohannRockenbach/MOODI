<?php

use App\Filament\Pages\CrearPedido;
use App\Models\Caja;
use App\Models\Category;
use App\Models\Cliente;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Sale;
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

function lpUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function lpCaja(User $user): Caja
{
    return Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $user->id,
        'restaurant_id' => 1,
    ]);
}

function lpCliente(int $points = 0): Cliente
{
    return Cliente::create([
        'restaurant_id' => 1,
        'name' => 'Cliente '.uniqid(),
        'email' => 'cliente-'.uniqid().'@example.com',
        'loyalty_points' => $points,
    ]);
}

function lpProduct(string $name, float $price): Product
{
    $category = Category::firstOrCreate(['name' => 'Bebidas']);

    return Product::factory()->create([
        'name' => $name,
        'price' => $price,
        'is_available' => true,
        'category_id' => $category->id,
        'restaurant_id' => 1,
        'recipe_id' => null,
        'stock' => 50,
    ]);
}

function lpTakeawayOrder(User $waiter, Product $product, int $qty, float $price, ?int $customerId = null): Order
{
    $order = Order::create([
        'restaurant_id' => 1,
        'table_id' => null,
        'waiter_id' => $waiter->id,
        'status' => 'pending',
        'type' => 'para_llevar',
        'customer_id' => $customerId,
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'price' => $price,
    ]);

    return $order;
}

// ─────────────────────────────────────────────────────────────
// 1) HAPPY PATH — ACUMULACIÓN
// ─────────────────────────────────────────────────────────────

it('acumula floor(total/100) puntos al cliente en una venta paid', function () {
    $mozo = lpUser('Mozo');
    $cliente = lpCliente(0);
    $product = lpProduct('Coca 250', 250.0);
    $order = lpTakeawayOrder($mozo, $product, 1, 250.0, $cliente->id); // total 250

    Sale::create([
        'order_id' => $order->id,
        'restaurant_id' => 1,
        'cashier_id' => $mozo->id,
        'total_amount' => 250.0,
        'payment_method' => 'cash',
        'status' => 'paid',
    ]);

    // floor(250/100) = 2 puntos.
    expect($cliente->fresh()->loyalty_points)->toBe(2);
});

// ─────────────────────────────────────────────────────────────
// 2) HAPPY PATH — CANJE
// ─────────────────────────────────────────────────────────────

it('canjea N puntos: descuenta N del total y decrementa el saldo del cliente', function () {
    $mozo = lpUser('Mozo');
    lpCaja($mozo);
    $cliente = lpCliente(50);
    $product = lpProduct('Burger 1000', 1000.0);
    $order = lpTakeawayOrder($mozo, $product, 1, 1000.0, $cliente->id); // 1000

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class)
        ->call('abrirCobro')
        ->call('selectAccountOrder', $order->id);

    expect($component->instance()->accountCustomerId())->toBe($cliente->id)
        ->and($component->instance()->loyaltyPointsAvailable())->toBe(50);

    $component->set('useLoyaltyPoints', true)
        ->set('loyaltyPointsToUse', '10');

    expect($component->instance()->loyaltyDiscountAmount())->toBe(10.0)
        ->and($component->instance()->accountTotal())->toBe(990.0)
        ->and($component->instance()->paymentsTotal)->toBe(990.0);

    $component->set('payments.0.method', 'card')->call('cobrar')->assertHasNoErrors();

    $sale = Sale::where('order_id', $order->id)->first();

    expect((float) $sale->total_amount)->toBe(990.0);

    // Saldo neto: 50 − 10 (canje) + floor(990/100) = 9 (acumulación sobre el
    // total ya descontado) = 49. La acumulación corre en Sale::created con
    // total_amount = 990 (DECISIÓN DEL ORQUESTADOR: se acumula sobre lo pagado).
    expect($cliente->fresh()->loyalty_points)->toBe(49);
});

// ─────────────────────────────────────────────────────────────
// 3) SEGURIDAD / AUTORIZACIÓN
// ─────────────────────────────────────────────────────────────

it('no acumula puntos si la venta no tiene cliente asociado', function () {
    $mozo = lpUser('Mozo');
    $product = lpProduct('Coca 500', 500.0);
    $order = lpTakeawayOrder($mozo, $product, 1, 500.0); // sin customer_id

    Sale::create([
        'order_id' => $order->id,
        'restaurant_id' => 1,
        'cashier_id' => $mozo->id,
        'total_amount' => 500.0,
        'payment_method' => 'cash',
        'status' => 'paid',
    ]);

    // No hay cliente → no se acumula ni se permite canje.
    expect(Cliente::count())->toBe(0);
    expect(Sale::first()->order->customer_id)->toBeNull();
});

it('limita el canje al total a pagar (no puede superar el total)', function () {
    $mozo = lpUser('Mozo');
    lpCaja($mozo);
    $cliente = lpCliente(100);
    $product = lpProduct('Café 30', 30.0);
    $order = lpTakeawayOrder($mozo, $product, 1, 30.0, $cliente->id); // total 30

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class)
        ->call('abrirCobro')
        ->call('selectAccountOrder', $order->id);

    // Pide canjear 100 puntos, pero el total a pagar es 30 → se limita a 30.
    $component->set('useLoyaltyPoints', true)
        ->set('loyaltyPointsToUse', '100');

    expect($component->instance()->loyaltyDiscountAmount())->toBe(30.0)
        ->and($component->instance()->accountTotal())->toBe(0.0)
        ->and($component->instance()->loyaltyPointsToApply())->toBe(30);
});

// ─────────────────────────────────────────────────────────────
// 4) EDGE CASES
// ─────────────────────────────────────────────────────────────

it('no acumula puntos en una venta anulada', function () {
    $mozo = lpUser('Mozo');
    $cliente = lpCliente(0);
    $product = lpProduct('Coca 250', 250.0);
    $order = lpTakeawayOrder($mozo, $product, 1, 250.0, $cliente->id);

    Sale::create([
        'order_id' => $order->id,
        'restaurant_id' => 1,
        'cashier_id' => $mozo->id,
        'total_amount' => 250.0,
        'payment_method' => 'cash',
        'status' => 'annulled',
        'annulled_at' => now(),
        'annulled_reason' => 'Prueba',
    ]);

    expect($cliente->fresh()->loyalty_points)->toBe(0);
});

it('no acumula puntos en una venta con total 0', function () {
    $mozo = lpUser('Mozo');
    $cliente = lpCliente(0);
    $product = lpProduct('Coca 0', 0.0);
    $order = lpTakeawayOrder($mozo, $product, 1, 0.0, $cliente->id);

    Sale::create([
        'order_id' => $order->id,
        'restaurant_id' => 1,
        'cashier_id' => $mozo->id,
        'total_amount' => 0.0,
        'payment_method' => 'cash',
        'status' => 'paid',
    ]);

    expect($cliente->fresh()->loyalty_points)->toBe(0);
});

it('es idempotente: actualizar la venta no re-suma puntos (solo created)', function () {
    $mozo = lpUser('Mozo');
    $cliente = lpCliente(0);
    $product = lpProduct('Coca 250', 250.0);
    $order = lpTakeawayOrder($mozo, $product, 1, 250.0, $cliente->id);

    $sale = Sale::create([
        'order_id' => $order->id,
        'restaurant_id' => 1,
        'cashier_id' => $mozo->id,
        'total_amount' => 250.0,
        'payment_method' => 'cash',
        'status' => 'paid',
    ]);

    expect($cliente->fresh()->loyalty_points)->toBe(2);

    // Un update posterior (ej. cambio de método de pago) NO re-suma.
    $sale->update(['total_amount' => 500.0, 'payment_method' => 'card']);

    expect($cliente->fresh()->loyalty_points)->toBe(2);
});
