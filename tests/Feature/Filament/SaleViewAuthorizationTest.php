<?php

use App\Filament\Resources\SaleResource\Pages\ListSales;
use App\Models\Caja;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\Table;
use App\Models\User;
use Livewire\Livewire;

it('hides cross-restaurant sale records and denies view action for them (authorization)', function () {
    $restaurantA = Restaurant::factory()->create();
    $restaurantB = Restaurant::factory()->create();

    $viewer = User::factory()->create([
        'email' => 'admin@moodi.com',
        'restaurant_id' => $restaurantA->id,
    ]);

    $waiterA = User::factory()->create(['restaurant_id' => $restaurantA->id]);
    $waiterB = User::factory()->create(['restaurant_id' => $restaurantB->id]);

    $tableA = Table::create([
        'number' => '21',
        'capacity' => 4,
        'location' => 'Salón',
        'status' => 'available',
        'waiter_id' => $waiterA->id,
        'restaurant_id' => $restaurantA->id,
    ]);

    $tableB = Table::create([
        'number' => '22',
        'capacity' => 4,
        'location' => 'Salón',
        'status' => 'available',
        'waiter_id' => $waiterB->id,
        'restaurant_id' => $restaurantB->id,
    ]);

    $cajaA = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $viewer->id,
        'restaurant_id' => $restaurantA->id,
    ]);

    $cajaB = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $waiterB->id,
        'restaurant_id' => $restaurantB->id,
    ]);

    $orderA = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $tableA->id,
        'waiter_id' => $waiterA->id,
        'restaurant_id' => $restaurantA->id,
    ]);

    $orderB = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $tableB->id,
        'waiter_id' => $waiterB->id,
        'restaurant_id' => $restaurantB->id,
    ]);

    $saleA = Sale::create([
        'total_amount' => 1000,
        'payment_method' => 'cash',
        'status' => 'paid',
        'order_id' => $orderA->id,
        'cashier_id' => $viewer->id,
        'restaurant_id' => $restaurantA->id,
        'caja_id' => $cajaA->id,
    ]);

    $saleB = Sale::create([
        'total_amount' => 2000,
        'payment_method' => 'card',
        'status' => 'paid',
        'order_id' => $orderB->id,
        'cashier_id' => $waiterB->id,
        'restaurant_id' => $restaurantB->id,
        'caja_id' => $cajaB->id,
    ]);

    Livewire::actingAs($viewer)
        ->test(ListSales::class)
        ->assertCanSeeTableRecords([$saleA])
        ->assertCanNotSeeTableRecords([$saleB])
        ->assertTableActionHidden('ver_detalle', $saleB);
});
