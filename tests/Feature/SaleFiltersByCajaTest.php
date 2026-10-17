<?php

use App\Filament\Resources\SaleResource;
use App\Models\Caja;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\Table;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('shows only sales from selected caja via table filter (happy path)', function () {
    $restaurant = Restaurant::factory()->create();

    $admin = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    Role::findOrCreate('super_admin', 'web');
    $admin->assignRole('super_admin');

    $table = Table::create([
        'number' => '10',
        'capacity' => 4,
        'location' => 'Salón',
        'status' => 'available',
        'waiter_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $cajaA = Caja::create([
        'opening_date' => now()->subDay(),
        'initial_balance' => 0,
        'status' => 'cerrada',
        'opening_user_id' => $admin->id,
        'closing_user_id' => $admin->id,
        'closing_date' => now()->subHours(12),
        'restaurant_id' => $restaurant->id,
    ]);

    $cajaB = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'cerrada',
        'opening_user_id' => $admin->id,
        'closing_user_id' => $admin->id,
        'closing_date' => now(),
        'restaurant_id' => $restaurant->id,
    ]);

    $orderA = Order::create([
        'status' => 'completed',
        'type' => 'salon',
        'table_id' => $table->id,
        'waiter_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $orderB = Order::create([
        'status' => 'completed',
        'type' => 'delivery',
        'table_id' => $table->id,
        'waiter_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
    ]);

    Sale::create([
        'total_amount' => 1200,
        'payment_method' => 'cash',
        'status' => 'paid',
        'order_id' => $orderA->id,
        'cashier_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $cajaA->id,
    ]);

    Sale::create([
        'total_amount' => 800,
        'payment_method' => 'card',
        'status' => 'paid',
        'order_id' => $orderB->id,
        'cashier_id' => $admin->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $cajaB->id,
    ]);

    $filtered = SaleResource::getEloquentQuery()->where('caja_id', $cajaA->id)->get();

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->caja_id)->toBe($cajaA->id);
});
