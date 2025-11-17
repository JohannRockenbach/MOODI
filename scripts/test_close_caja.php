<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Caja;
use App\Models\Sale;
use App\Models\User;
use App\Models\Restaurant;

$restaurant = Restaurant::first();
$user = User::first();
if (! $restaurant || ! $user) {
    echo "No restaurant or user found. Ensure DB has seed data.\n";
    exit(1);
}

$caja = Caja::create([
    'restaurant_id' => $restaurant->id,
    'opening_date' => now(),
    'initial_balance' => 10,
    'status' => 'abierta',
    'opening_user_id' => $user->id,
]);

echo "Created caja id: {$caja->id}\n";


// Ensure there is an order for the sale (sales.order_id is NOT NULL in the schema)
use App\Models\Order;
use App\Models\Table as Mesa;

$table = Mesa::where('restaurant_id', $restaurant->id)->first();
if (! $table) {
    $table = Mesa::create([
        'number' => 'T-1',
        'capacity' => 4,
        'restaurant_id' => $restaurant->id,
    ]);
}

$order = Order::create([
    'status' => 'en_proceso',
    'type' => 'salon',
    'table_id' => $table->id,
    'waiter_id' => $user->id,
    'restaurant_id' => $restaurant->id,
]);

$sale = Sale::create([
    'total_amount' => 50,
    'payment_method' => 'cash',
    'order_id' => $order->id,
    'cashier_id' => $user->id,
    'restaurant_id' => $restaurant->id,
    'caja_id' => $caja->id,
]);

echo "Created sale id: {$sale->id}\n";

$totalSales = $caja->sales()->sum('total_amount');

$caja->update([
    'closing_date' => now(),
    'closing_user_id' => $user->id,
    'status' => 'cerrada',
    'total_sales' => $totalSales,
    'final_balance' => $caja->initial_balance + $totalSales,
]);

echo "Caja closed. final_balance: {$caja->fresh()->final_balance}\n";
