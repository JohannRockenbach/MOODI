<?php

use App\Actions\Sales\AnnulSaleAction;
use App\Actions\Sales\RestoreSaleAnnulmentAction;
use App\Models\Caja;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\Table;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

it('annuls a paid sale and excludes it from caja net total (happy path)', function () {
    $restaurant = Restaurant::factory()->create();

    $admin = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);
    Role::findOrCreate('super_admin', 'web');
    $admin->assignRole('super_admin');

    $waiter = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $table = Table::create([
        'number' => '1',
        'capacity' => 4,
        'location' => 'Salón',
        'status' => 'available',
        'waiter_id' => $waiter->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $caja = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 1000,
        'status' => 'abierta',
        'opening_user_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $orderA = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $table->id,
        'waiter_id' => $waiter->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $orderB = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $table->id,
        'waiter_id' => $waiter->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $saleToAnnul = Sale::create([
        'total_amount' => 1500,
        'payment_method' => 'cash',
        'status' => 'paid',
        'order_id' => $orderA->id,
        'cashier_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $caja->id,
    ]);

    Sale::create([
        'total_amount' => 500,
        'payment_method' => 'card',
        'status' => 'paid',
        'order_id' => $orderB->id,
        'cashier_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $caja->id,
    ]);

    app(AnnulSaleAction::class)($saleToAnnul, $admin, 'Error de carga');

    $saleToAnnul->refresh();

    expect($saleToAnnul->status)->toBe('annulled')
        ->and($saleToAnnul->annulled_at)->not->toBeNull()
        ->and($saleToAnnul->annulled_by)->toBe($admin->id)
        ->and($saleToAnnul->annulled_reason)->toBe('Error de carga')
        ->and((float) $caja->fresh()->total_sales)->toBe(500.0);
});

it('denies annulment for unauthorized user (security)', function () {
    $restaurant = Restaurant::factory()->create();

    $owner = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $unauthorized = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $table = Table::create([
        'number' => '2',
        'capacity' => 4,
        'location' => 'Salón',
        'status' => 'available',
        'waiter_id' => $owner->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $caja = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $owner->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $order = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $table->id,
        'waiter_id' => $owner->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $sale = Sale::create([
        'total_amount' => 200,
        'payment_method' => 'cash',
        'status' => 'paid',
        'order_id' => $order->id,
        'cashier_id' => $owner->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $caja->id,
    ]);

    expect(fn () => app(AnnulSaleAction::class)($sale, $unauthorized, 'Intento sin permiso'))
        ->toThrow(AuthorizationException::class);

    expect($sale->fresh()->status)->toBe('paid');
});

it('prevents re-annulling an already annulled sale (edge)', function () {
    $restaurant = Restaurant::factory()->create();

    $admin = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);
    Role::findOrCreate('super_admin', 'web');
    $admin->assignRole('super_admin');

    $table = Table::create([
        'number' => '3',
        'capacity' => 4,
        'location' => 'Barra',
        'status' => 'available',
        'waiter_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $caja = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $order = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $table->id,
        'waiter_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $sale = Sale::create([
        'total_amount' => 300,
        'payment_method' => 'cash',
        'status' => 'paid',
        'order_id' => $order->id,
        'cashier_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $caja->id,
    ]);

    app(AnnulSaleAction::class)($sale, $admin, 'Primer motivo');

    $firstAnnulledAt = $sale->fresh()->annulled_at;

    expect(fn () => app(AnnulSaleAction::class)($sale->fresh(), $admin, 'Segundo motivo'))
        ->toThrow(DomainException::class, 'La venta ya fue anulada.');

    $sale->refresh();

    expect($sale->annulled_reason)->toBe('Primer motivo')
        ->and($sale->annulled_at?->eq($firstAnnulledAt))->toBeTrue();
});

it('restores an annulled sale and re-includes it in caja net total (happy path)', function () {
    $restaurant = Restaurant::factory()->create();

    $admin = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);
    Role::findOrCreate('super_admin', 'web');
    $admin->assignRole('super_admin');

    $table = Table::create([
        'number' => '4',
        'capacity' => 4,
        'location' => 'Salón',
        'status' => 'available',
        'waiter_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $caja = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $order = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $table->id,
        'waiter_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $sale = Sale::create([
        'total_amount' => 800,
        'payment_method' => 'cash',
        'status' => 'paid',
        'order_id' => $order->id,
        'cashier_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $caja->id,
    ]);

    app(AnnulSaleAction::class)($sale, $admin, 'Carga duplicada');
    expect((float) $caja->fresh()->total_sales)->toBe(0.0);

    app(RestoreSaleAnnulmentAction::class)($sale->fresh(), $admin);

    $sale->refresh();

    expect($sale->status)->toBe('paid')
        ->and($sale->annulled_at)->toBeNull()
        ->and($sale->annulled_by)->toBeNull()
        ->and($sale->annulled_reason)->toBeNull()
        ->and((float) $caja->fresh()->total_sales)->toBe(800.0);
});

it('denies restore for unauthorized user (security)', function () {
    $restaurant = Restaurant::factory()->create();

    $superAdmin = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);
    Role::findOrCreate('super_admin', 'web');
    $superAdmin->assignRole('super_admin');

    $unauthorized = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $table = Table::create([
        'number' => '5',
        'capacity' => 4,
        'location' => 'Patio',
        'status' => 'available',
        'waiter_id' => $superAdmin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $caja = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $superAdmin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $order = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $table->id,
        'waiter_id' => $superAdmin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $sale = Sale::create([
        'total_amount' => 350,
        'payment_method' => 'card',
        'status' => 'paid',
        'order_id' => $order->id,
        'cashier_id' => $superAdmin->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $caja->id,
    ]);

    app(AnnulSaleAction::class)($sale, $superAdmin, 'Error administrativo');

    expect(fn () => app(RestoreSaleAnnulmentAction::class)($sale->fresh(), $unauthorized))
        ->toThrow(AuthorizationException::class);

    expect($sale->fresh()->status)->toBe('annulled')
        ->and($sale->fresh()->annulled_at)->not->toBeNull();
});

it('prevents restoring a non-annulled sale (edge)', function () {
    $restaurant = Restaurant::factory()->create();

    $admin = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);
    Role::findOrCreate('super_admin', 'web');
    $admin->assignRole('super_admin');

    $table = Table::create([
        'number' => '6',
        'capacity' => 4,
        'location' => 'Salón',
        'status' => 'available',
        'waiter_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $caja = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $order = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $table->id,
        'waiter_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $sale = Sale::create([
        'total_amount' => 410,
        'payment_method' => 'transfer',
        'status' => 'paid',
        'order_id' => $order->id,
        'cashier_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $caja->id,
    ]);

    expect(fn () => app(RestoreSaleAnnulmentAction::class)($sale->fresh(), $admin))
        ->toThrow(DomainException::class, 'La venta no está anulada.');

    expect($sale->fresh()->status)->toBe('paid')
        ->and($sale->fresh()->annulled_at)->toBeNull();
});
