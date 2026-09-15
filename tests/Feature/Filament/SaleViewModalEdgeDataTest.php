<?php

use App\Filament\Resources\SaleResource\Pages\ListSales;
use App\Models\Caja;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\Table;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('renders sale view modal with edge data when order items and discounts are missing', function () {
    $restaurant = Restaurant::factory()->create();

    $viewer = User::factory()->create([
        'email' => 'admin@moodi.com',
        'restaurant_id' => $restaurant->id,
    ]);

    Role::findOrCreate('super_admin', 'web');
    $viewer->assignRole('super_admin');

    $table = Table::create([
        'number' => '31',
        'capacity' => 4,
        'location' => 'Patio',
        'status' => 'available',
        'waiter_id' => $viewer->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $caja = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $viewer->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $order = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $table->id,
        'waiter_id' => $viewer->id,
        'restaurant_id' => $restaurant->id,
    ]);

    $sale = Sale::create([
        'total_amount' => 1200,
        'payment_method' => 'transfer',
        'status' => 'paid',
        'order_id' => $order->id,
        'cashier_id' => $viewer->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $caja->id,
    ]);

    Livewire::actingAs($viewer)
        ->test(ListSales::class)
        ->assertCanSeeTableRecords([$sale])
        ->mountTableAction('ver_detalle', $sale)
        ->assertDispatched('open-modal')
        ->assertSee('Detalle de venta #'.$sale->id)
        ->assertSee('Sin ítems cargados para esta venta');
});
