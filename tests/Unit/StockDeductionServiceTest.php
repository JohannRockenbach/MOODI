<?php

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use App\Services\StockDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->restaurant = Restaurant::factory()->create();

    $this->user = User::factory()->create([
        'restaurant_id' => $this->restaurant->id,
    ]);

    $this->table = Table::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'number' => 1,
    ]);

    $this->category = Category::factory()->create([
        'name' => 'Hamburguesas '.uniqid(),
    ]);

    $this->service = new StockDeductionService();
});

function makeServiceOrder(Restaurant $restaurant, User $user, Table $table, Product $product, int $qty, float $price): Order
{
    $order = Order::create([
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'waiter_id' => $user->id,
        'status' => 'pending',
        'stock_deducted' => false,
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'price' => $price,
    ]);

    return $order;
}

test('deductForOrder descuenta stock FEFO por lotes y marca el pedido', function () {
    $ingredient = Ingredient::create([
        'name' => 'Pan',
        'measurement_unit' => 'unidades',
        'reorder_point' => 0,
        'restaurant_id' => $this->restaurant->id,
    ]);

    $batchOld = IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 5,
        'expiration_date' => now()->addDays(2),
    ]);

    $batchNew = IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 20,
        'expiration_date' => now()->addDays(10),
    ]);

    $recipe = Recipe::create(['name' => 'Burger '.uniqid(), 'instructions' => 'Preparar']);
    $recipe->ingredients()->attach($ingredient->id, ['required_amount' => 2]);

    $product = Product::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'recipe_id' => $recipe->id,
        'category_id' => $this->category->id,
        'price' => 500,
        'stock' => 0,
    ]);

    $order = makeServiceOrder($this->restaurant, $this->user, $this->table, $product, 3, 500);

    $this->service->deductForOrder($order);

    // FEFO: primero consume el lote más viejo (5) y el resto (1) del nuevo.
    expect((float) $batchOld->fresh()->quantity)->toBe(0.0)
        ->and((float) $batchNew->fresh()->quantity)->toBe(19.0)
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});

test('deductForOrder es idempotente: no vuelve a descontar si stock_deducted ya es true', function () {
    $product = Product::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'recipe_id' => null,
        'category_id' => $this->category->id,
        'price' => 200,
        'stock' => 50,
    ]);

    $order = makeServiceOrder($this->restaurant, $this->user, $this->table, $product, 2, 200);
    $order->stock_deducted = true;
    $order->saveQuietly();

    $this->service->deductForOrder($order);

    expect((int) $product->fresh()->stock)->toBe(50)
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});