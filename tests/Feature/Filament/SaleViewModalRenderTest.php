<?php

use App\Filament\Resources\SaleResource\Pages\ListSales;
use App\Models\Caja;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('renders sale view modal and logs lightweight render timing (happy path)', function () {
    $restaurant = Restaurant::factory()->create();

    $viewer = User::factory()->create([
        'email' => 'admin@moodi.com',
        'restaurant_id' => $restaurant->id,
    ]);

    Role::findOrCreate('super_admin', 'web');
    $viewer->assignRole('super_admin');

    $waiter = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $table = Table::create([
        'number' => '11',
        'capacity' => 4,
        'location' => 'Salón',
        'status' => 'available',
        'waiter_id' => $waiter->id,
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
        'type' => 'delivery',
        'table_id' => $table->id,
        'waiter_id' => $waiter->id,
        'restaurant_id' => $restaurant->id,
        'delivery_phone' => '1122334455',
        'delivery_address' => 'Av. Siempre Viva 123',
        'customer_name' => 'Cliente Test',
    ]);

    $category = Category::create([
        'name' => 'Render test category',
        'description' => 'Categoría de prueba',
    ]);

    $product = Product::create([
        'name' => 'Producto modal',
        'description' => 'Producto para test de modal',
        'price' => 1500,
        'is_available' => true,
        'category_id' => $category->id,
        'restaurant_id' => $restaurant->id,
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 1500,
    ]);

    $sale = Sale::create([
        'total_amount' => 2700,
        'payment_method' => 'cash',
        'status' => 'paid',
        'order_id' => $order->id,
        'cashier_id' => $viewer->id,
        'restaurant_id' => $restaurant->id,
        'caja_id' => $caja->id,
    ]);

    $discount = Discount::create([
        'name' => 'Promo 10',
        'code' => 'PROMO10',
        'description' => '10% off',
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
        'restaurant_id' => $restaurant->id,
    ]);

    $sale->discounts()->attach($discount->id, ['amount_discounted' => 300]);

    Log::spy();

    Livewire::actingAs($viewer)
        ->test(ListSales::class)
        ->assertCanSeeTableRecords([$sale])
        ->mountTableAction('ver_detalle', $sale)
        ->assertDispatched('open-modal')
        ->assertSee('Detalle de venta #'.$sale->id)
        ->assertSee('Producto modal')
        ->assertSee('-$300,00', false);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context): bool {
            return $message === 'sales.view_modal.rendered'
                && ($context['sale_id'] ?? null) !== null
                && array_key_exists('duration_ms', $context)
                && is_numeric($context['duration_ms']);
        })
        ->once();
});
