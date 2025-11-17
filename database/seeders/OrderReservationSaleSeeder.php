<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\Product;
use App\Models\User;
use App\Models\Table;
use App\Models\Restaurant;

class OrderReservationSaleSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::first();
        $user = User::first();
        $table = Table::first();
        $product = Product::first();

        if (! $restaurant || ! $user || ! $table) {
            return; // nothing to seed against
        }

        // Create a reservation (idempotent)
        $reservationTime = now()->addDay();
        $reservation = Reservation::firstOrCreate([
            'table_id' => $table->id,
            'customer_id' => $user->id,
            'reservation_time' => $reservationTime,
        ],[
            'guest_count' => 2,
            'status' => 'pendiente',
            'restaurant_id' => $restaurant->id,
        ]);

        // Create an order (idempotent)
        $order = Order::firstOrCreate([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'waiter_id' => $user->id,
            'status' => 'en_proceso',
        ],[
            'type' => 'salon',
        ]);

        if ($product) {
            OrderProduct::updateOrCreate([
                'order_id' => $order->id,
                'product_id' => $product->id,
            ],[
                'quantity' => 1,
                'notes' => 'Por favor sin cebolla',
            ]);
        }

        // Create a sale for the order (idempotent by order)
        Sale::updateOrCreate([
            'order_id' => $order->id,
        ],[
            'total_amount' => $product ? ($product->price ?? 0) : 0,
            'payment_method' => 'efectivo',
            'cashier_id' => $user->id,
            'restaurant_id' => $restaurant->id,
        ]);
    }
}
