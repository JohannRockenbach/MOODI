<?php

use App\Events\OrderProcessing;
use App\Models\Category;
use App\Models\Cliente;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->restaurant = Restaurant::factory()->create();

    $this->user = User::factory()->create([
        'restaurant_id' => $this->restaurant->id,
    ]);

    $this->customer = Cliente::query()->create([
        'name' => 'Cliente Test',
        'email' => 'cliente+'.uniqid().'@test.local',
        'phone' => '111111111',
        'restaurant_id' => $this->restaurant->id,
    ]);

    $this->table = Table::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'number' => 1,
    ]);

    $this->category = Category::factory()->create([
        'name' => 'Hamburguesas '.uniqid(),
    ]);
});

test('order creation reduces stock for products with recipe using FEFO logic', function () {
    $ingredient1 = Ingredient::query()->create([
        'name' => 'Pan',
        'measurement_unit' => 'unidades',
        'reorder_point' => 0,
        'restaurant_id' => $this->restaurant->id,
    ]);

    $ingredient2 = Ingredient::query()->create([
        'name' => 'Carne',
        'measurement_unit' => 'gramos',
        'reorder_point' => 0,
        'restaurant_id' => $this->restaurant->id,
    ]);

    $batch1 = IngredientBatch::query()->create([
        'ingredient_id' => $ingredient1->id,
        'quantity' => 10,
        'expiration_date' => now()->addDays(5),
    ]);

    $batch2 = IngredientBatch::query()->create([
        'ingredient_id' => $ingredient1->id,
        'quantity' => 15,
        'expiration_date' => now()->addDays(10),
    ]);

    $batch3 = IngredientBatch::query()->create([
        'ingredient_id' => $ingredient2->id,
        'quantity' => 500,
        'expiration_date' => now()->addDays(7),
    ]);

    $recipe = Recipe::query()->create([
        'name' => 'Hamburguesa Clásica '.uniqid(),
        'instructions' => 'Preparar hamburguesa',
    ]);

    $recipe->ingredients()->attach($ingredient1->id, ['required_amount' => 2]);
    $recipe->ingredients()->attach($ingredient2->id, ['required_amount' => 150]);

    $product = Product::factory()->create([
        'name' => 'Hamburguesa Clásica',
        'category_id' => $this->category->id,
        'restaurant_id' => $this->restaurant->id,
        'recipe_id' => $recipe->id,
        'price' => 500,
        'stock' => 0,
    ]);

    $order = Order::create([
        'restaurant_id' => $this->restaurant->id,
        'customer_id' => $this->customer->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->user->id,
        'status' => 'pending',
        'stock_deducted' => false,
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 3,
        'price' => 500,
    ]);

    event(new OrderProcessing($order));

    expect((float) $batch1->fresh()->quantity)->toBe(4.0)
        ->and((float) $batch2->fresh()->quantity)->toBe(15.0)
        ->and((float) $batch3->fresh()->quantity)->toBe(50.0)
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});

test('order creation reduces direct stock for products without recipe', function () {
    $product = Product::factory()->create([
        'name' => 'Coca-Cola 350ml',
        'category_id' => $this->category->id,
        'restaurant_id' => $this->restaurant->id,
        'recipe_id' => null,
        'price' => 200,
        'stock' => 50,
    ]);

    $order = Order::create([
        'restaurant_id' => $this->restaurant->id,
        'customer_id' => $this->customer->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->user->id,
        'status' => 'pending',
        'stock_deducted' => false,
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 3,
        'price' => 200,
    ]);

    event(new OrderProcessing($order));

    expect((int) $product->fresh()->stock)->toBe(47)
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});

test('stock is not deducted twice for the same order', function () {
    $product = Product::factory()->create([
        'name' => 'Sprite 350ml',
        'category_id' => $this->category->id,
        'restaurant_id' => $this->restaurant->id,
        'recipe_id' => null,
        'price' => 200,
        'stock' => 50,
    ]);

    $order = Order::create([
        'restaurant_id' => $this->restaurant->id,
        'customer_id' => $this->customer->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->user->id,
        'status' => 'completed',
        'stock_deducted' => true,
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 200,
    ]);

    event(new OrderProcessing($order));

    expect((int) $product->fresh()->stock)->toBe(50)
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});

test('FEFO logic uses oldest batch first when multiple batches available', function () {
    $ingredient = Ingredient::query()->create([
        'name' => 'Tomate',
        'measurement_unit' => 'kg',
        'reorder_point' => 0,
        'restaurant_id' => $this->restaurant->id,
    ]);

    $batchOld = IngredientBatch::query()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 5,
        'expiration_date' => now()->addDays(2),
    ]);

    $batchMid = IngredientBatch::query()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 10,
        'expiration_date' => now()->addDays(5),
    ]);

    $batchNew = IngredientBatch::query()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 20,
        'expiration_date' => now()->addDays(10),
    ]);

    $recipe = Recipe::query()->create([
        'name' => 'Ensalada '.uniqid(),
        'instructions' => 'Cortar tomate',
    ]);

    $recipe->ingredients()->attach($ingredient->id, ['required_amount' => 0.5]);

    $product = Product::factory()->create([
        'name' => 'Ensalada Fresca',
        'category_id' => $this->category->id,
        'restaurant_id' => $this->restaurant->id,
        'recipe_id' => $recipe->id,
        'price' => 300,
    ]);

    $order = Order::create([
        'restaurant_id' => $this->restaurant->id,
        'customer_id' => $this->customer->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->user->id,
        'status' => 'pending',
        'stock_deducted' => false,
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 8,
        'price' => 300,
    ]);

    event(new OrderProcessing($order));

    expect((float) $batchOld->fresh()->quantity)->toBe(1.0)
        ->and((float) $batchMid->fresh()->quantity)->toBe(10.0)
        ->and((float) $batchNew->fresh()->quantity)->toBe(20.0);
});

test('stock reduction handles insufficient batch stock gracefully', function () {
    $ingredient = Ingredient::query()->create([
        'name' => 'Lechuga',
        'measurement_unit' => 'unidades',
        'reorder_point' => 0,
        'restaurant_id' => $this->restaurant->id,
    ]);

    $batch = IngredientBatch::query()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 3,
        'expiration_date' => now()->addDays(5),
    ]);

    $recipe = Recipe::query()->create([
        'name' => 'Burger con Lechuga '.uniqid(),
        'instructions' => 'Agregar lechuga',
    ]);

    $recipe->ingredients()->attach($ingredient->id, ['required_amount' => 1]);

    $product = Product::factory()->create([
        'name' => 'Burger Especial',
        'category_id' => $this->category->id,
        'restaurant_id' => $this->restaurant->id,
        'recipe_id' => $recipe->id,
        'price' => 600,
    ]);

    $order = Order::create([
        'restaurant_id' => $this->restaurant->id,
        'customer_id' => $this->customer->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->user->id,
        'status' => 'pending',
        'stock_deducted' => false,
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 5,
        'price' => 600,
    ]);

    event(new OrderProcessing($order));

    expect((float) $batch->fresh()->quantity)->toBe(0.0)
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});
