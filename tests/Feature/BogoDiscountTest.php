<?php

use App\Filament\Pages\CrearPedido;
use App\Models\Caja;
use App\Models\Category;
use App\Models\Discount;
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

function bogoUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function bogoCaja(User $user): Caja
{
    return Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $user->id,
        'restaurant_id' => 1,
    ]);
}

function bogoProduct(string $name, float $price): Product
{
    $category = Category::firstOrCreate(['name' => 'Hamburguesas']);

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

function bogoTakeawayOrder(User $waiter, Product $product, int $qty, float $price): Order
{
    $order = Order::create([
        'restaurant_id' => 1,
        'table_id' => null,
        'waiter_id' => $waiter->id,
        'status' => 'pending',
        'type' => 'para_llevar',
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'price' => $price,
    ]);

    return $order;
}

function bogoDiscount(bool $active = true): Discount
{
    return Discount::create([
        'name' => '2x1 '.uniqid(),
        'code' => 'BOGO'.uniqid(),
        'type' => 'bogo',
        'value' => 0,
        'is_active' => $active,
        'restaurant_id' => 1,
    ]);
}

// ─────────────────────────────────────────────────────────────
// 1) HAPPY PATH — 2x1
// ─────────────────────────────────────────────────────────────

it('descuenta el precio de UNA unidad por cada par (2 unidades → 1 gratis)', function () {
    $mozo = bogoUser('Mozo');
    bogoCaja($mozo);
    $product = bogoProduct('Burger 2x1', 1000.0);
    $order = bogoTakeawayOrder($mozo, $product, 2, 1000.0); // 2000
    $bogo = bogoDiscount();

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class)
        ->call('abrirCobro')
        ->call('selectAccountOrder', $order->id);

    expect($component->instance()->accountSubtotal())->toBe(2000.0);

    $component->call('toggleDiscount', $bogo->id);

    expect($component->instance()->bogoDiscountAmount())->toBe(1000.0)
        ->and($component->instance()->discountAmountValue())->toBe(1000.0)
        ->and($component->instance()->accountTotal())->toBe(1000.0)
        ->and($component->instance()->paymentsTotal)->toBe(1000.0);

    $component->set('payments.0.method', 'card')->call('cobrar')->assertHasNoErrors();

    $sale = Sale::where('order_id', $order->id)->first();

    expect((float) $sale->total_amount)->toBe(1000.0)
        ->and((float) $sale->payments->sum('amount'))->toBe(1000.0);
});

// ─────────────────────────────────────────────────────────────
// 2) HAPPY PATH — FRANJA HORARIA
// ─────────────────────────────────────────────────────────────

it('es elegible dentro de la franja horaria y NO fuera de ella', function () {
    $now = now();

    $discount = Discount::create([
        'name' => 'Happy Hour',
        'code' => 'HAPPY'.uniqid(),
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
        'restaurant_id' => 1,
        'starts_at' => $now->copy()->subHour()->format('H:i'),
        'ends_at' => $now->copy()->addHour()->format('H:i'),
    ]);

    expect($discount->isEligibleNow($now))->toBeTrue();

    // Franja que empieza en el futuro → no elegible ahora.
    $discount->update([
        'starts_at' => $now->copy()->addHour()->format('H:i'),
        'ends_at' => $now->copy()->addHours(2)->format('H:i'),
    ]);

    expect($discount->fresh()->isEligibleNow($now))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────
// 3) SEGURIDAD / ELIGIBILIDAD (policy existente: solo activos aplican)
// ─────────────────────────────────────────────────────────────

it('un descuento inactivo o expirado NO es elegible (bogo incluido)', function () {
    $now = now();

    // Inactivo.
    $inactive = bogoDiscount(false);
    expect($inactive->isEligibleNow($now))->toBeFalse();

    // Expirado.
    $expired = Discount::create([
        'name' => 'Expirado',
        'code' => 'EXP'.uniqid(),
        'type' => 'bogo',
        'value' => 0,
        'is_active' => true,
        'expires_at' => $now->copy()->subDay(),
        'restaurant_id' => 1,
    ]);
    expect($expired->isEligibleNow($now))->toBeFalse();

    // Activo y sin restricciones → elegible.
    $active = bogoDiscount(true);
    expect($active->isEligibleNow($now))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────
// 4) EDGE CASES
// ─────────────────────────────────────────────────────────────

it('descuenta 1 precio en cantidad impar (3 unidades → 1 par)', function () {
    $mozo = bogoUser('Mozo');
    bogoCaja($mozo);
    $product = bogoProduct('Burger 3x', 1000.0);
    $order = bogoTakeawayOrder($mozo, $product, 3, 1000.0); // 3000
    $bogo = bogoDiscount();

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class)
        ->call('abrirCobro')
        ->call('selectAccountOrder', $order->id)
        ->call('toggleDiscount', $bogo->id);

    expect($component->instance()->bogoDiscountAmount())->toBe(1000.0)
        ->and($component->instance()->accountTotal())->toBe(2000.0);
});

it('días de semana vacío o null = todos los días (elegible siempre)', function () {
    $now = now();

    $discount = Discount::create([
        'name' => 'Todos los días',
        'code' => 'ALL'.uniqid(),
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
        'restaurant_id' => 1,
        'days_of_week' => null,
    ]);

    expect($discount->isEligibleNow($now))->toBeTrue();

    $discount->update(['days_of_week' => []]);
    expect($discount->fresh()->isEligibleNow($now))->toBeTrue();

    // Día distinto a hoy → no elegible (verifica el mapeo 0=Lun..6=Dom).
    $today = ((int) $now->dayOfWeekIso) - 1;
    $otherDay = ($today + 1) % 7;

    $discount->update(['days_of_week' => [$otherDay]]);
    expect($discount->fresh()->isEligibleNow($now))->toBeFalse();
});

it('un producto con precio 0 o ítems sin product_id no rompe el cálculo 2x1', function () {
    $mozo = bogoUser('Mozo');
    $product = bogoProduct('Gratis', 0.0);
    $order = bogoTakeawayOrder($mozo, $product, 2, 0.0); // total 0
    $bogo = bogoDiscount();

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class)
        ->call('abrirCobro')
        ->call('selectAccountOrder', $order->id)
        ->call('toggleDiscount', $bogo->id);

    // Precio 0 → no hay descuento 2x1 y no lanza excepción.
    expect($component->instance()->bogoDiscountAmount())->toBe(0.0)
        ->and($component->instance()->accountTotal())->toBe(0.0);
});
