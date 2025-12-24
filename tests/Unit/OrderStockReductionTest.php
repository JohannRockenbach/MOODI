<?php

use App\Events\OrderProcessing;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Cliente;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;

beforeEach(function () {
    // Crear datos base necesarios para las pruebas
    $this->restaurant = Restaurant::factory()->create();
    
    $this->user = User::factory()->create([
        'restaurant_id' => $this->restaurant->id,
    ]);
    
    $this->customer = Cliente::factory()->create([
        'restaurant_id' => $this->restaurant->id,
    ]);
    
    $this->table = Table::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'number' => 1,
    ]);
    
    $this->category = Category::factory()->create([
        'name' => 'Hamburguesas',
    ]);
});

test('order creation reduces stock for products with recipe using FEFO logic', function () {
    // Crear ingredientes
    $ingredient1 = Ingredient::factory()->create([
        'name' => 'Pan',
        'measurement_unit' => 'unidades',
        'restaurant_id' => $this->restaurant->id,
    ]);
    
    $ingredient2 = Ingredient::factory()->create([
        'name' => 'Carne',
        'measurement_unit' => 'gramos',
        'restaurant_id' => $this->restaurant->id,
    ]);
    
    // Crear lotes con diferentes fechas de vencimiento
    $batch1 = Batch::factory()->create([
        'ingredient_id' => $ingredient1->id,
        'quantity' => 10,
        'expiration_date' => now()->addDays(5), // Vence primero
    ]);
    
    $batch2 = Batch::factory()->create([
        'ingredient_id' => $ingredient1->id,
        'quantity' => 15,
        'expiration_date' => now()->addDays(10), // Vence después
    ]);
    
    $batch3 = Batch::factory()->create([
        'ingredient_id' => $ingredient2->id,
        'quantity' => 500,
        'expiration_date' => now()->addDays(7),
    ]);
    
    // Crear receta
    $recipe = Recipe::factory()->create([
        'name' => 'Hamburguesa Clásica',
        'instructions' => 'Preparar hamburguesa',
        'restaurant_id' => $this->restaurant->id,
    ]);
    
    // Asociar ingredientes a la receta
    $recipe->ingredients()->attach($ingredient1->id, ['required_amount' => 2]); // 2 panes
    $recipe->ingredients()->attach($ingredient2->id, ['required_amount' => 150]); // 150g de carne
    
    // Crear producto con receta
    $product = Product::factory()->create([
        'name' => 'Hamburguesa Clásica',
        'category_id' => $this->category->id,
        'recipe_id' => $recipe->id,
        'price' => 500,
        'stock' => 0, // Stock no se usa para productos con receta
    ]);
    
    // Crear orden
    $order = Order::create([
        'restaurant_id' => $this->restaurant->id,
        'customer_id' => $this->customer->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->user->id,
        'status' => 'pending',
        'total' => 1500,
        'stock_deducted' => false,
    ]);
    
    // Agregar productos a la orden
    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 3, // 3 hamburguesas
        'unit_price' => 500,
        'subtotal' => 1500,
    ]);
    
    // Disparar evento para reducir stock
    event(new OrderProcessing($order));
    
    // Verificar que el stock se redujo correctamente usando FEFO
    // Para 3 hamburguesas: 3 * 2 panes = 6 panes necesarios
    expect($batch1->fresh()->quantity)->toBe(4.0) // 10 - 6 = 4 (usa el lote que vence primero)
        ->and($batch2->fresh()->quantity)->toBe(15.0) // No se toca aún
        ->and($batch3->fresh()->quantity)->toBe(50.0) // 500 - (3 * 150) = 50
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});

test('order creation reduces direct stock for products without recipe', function () {
    // Crear producto sin receta (stock directo)
    $product = Product::factory()->create([
        'name' => 'Coca-Cola 350ml',
        'category_id' => $this->category->id,
        'recipe_id' => null, // Sin receta
        'price' => 200,
        'stock' => 50, // Stock directo
    ]);
    
    // Crear orden
    $order = Order::create([
        'restaurant_id' => $this->restaurant->id,
        'customer_id' => $this->customer->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->user->id,
        'status' => 'pending',
        'total' => 600,
        'stock_deducted' => false,
    ]);
    
    // Agregar productos a la orden
    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 3, // 3 Coca-Colas
        'unit_price' => 200,
        'subtotal' => 600,
    ]);
    
    // Disparar evento para reducir stock
    event(new OrderProcessing($order));
    
    // Verificar que el stock directo se redujo
    expect($product->fresh()->stock)->toBe(47) // 50 - 3 = 47
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});

test('stock is not deducted twice for the same order', function () {
    // Crear producto sin receta
    $product = Product::factory()->create([
        'name' => 'Sprite 350ml',
        'category_id' => $this->category->id,
        'recipe_id' => null,
        'price' => 200,
        'stock' => 50,
    ]);
    
    // Crear orden ya procesada
    $order = Order::create([
        'restaurant_id' => $this->restaurant->id,
        'customer_id' => $this->customer->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->user->id,
        'status' => 'completed',
        'total' => 400,
        'stock_deducted' => true, // Ya fue descontado
    ]);
    
    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 200,
        'subtotal' => 400,
    ]);
    
    // Intentar disparar evento de nuevo
    event(new OrderProcessing($order));
    
    // Verificar que el stock NO cambió
    expect($product->fresh()->stock)->toBe(50) // Stock sin cambios
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});

test('FEFO logic uses oldest batch first when multiple batches available', function () {
    // Crear ingrediente
    $ingredient = Ingredient::factory()->create([
        'name' => 'Tomate',
        'measurement_unit' => 'kg',
        'restaurant_id' => $this->restaurant->id,
    ]);
    
    // Crear 3 lotes con diferentes fechas
    $batchOld = Batch::factory()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 5,
        'expiration_date' => now()->addDays(2), // Vence primero
    ]);
    
    $batchMid = Batch::factory()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 10,
        'expiration_date' => now()->addDays(5), // Vence en medio
    ]);
    
    $batchNew = Batch::factory()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 20,
        'expiration_date' => now()->addDays(10), // Vence último
    ]);
    
    // Crear receta
    $recipe = Recipe::factory()->create([
        'name' => 'Ensalada',
        'instructions' => 'Cortar tomate',
        'restaurant_id' => $this->restaurant->id,
    ]);
    
    $recipe->ingredients()->attach($ingredient->id, ['required_amount' => 0.5]); // 0.5 kg por ensalada
    
    // Crear producto
    $product = Product::factory()->create([
        'name' => 'Ensalada Fresca',
        'category_id' => $this->category->id,
        'recipe_id' => $recipe->id,
        'price' => 300,
    ]);
    
    // Crear orden
    $order = Order::create([
        'restaurant_id' => $this->restaurant->id,
        'customer_id' => $this->customer->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->user->id,
        'status' => 'pending',
        'total' => 2400,
        'stock_deducted' => false,
    ]);
    
    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 8, // 8 ensaladas = 4 kg de tomate
        'unit_price' => 300,
        'subtotal' => 2400,
    ]);
    
    // Disparar evento
    event(new OrderProcessing($order));
    
    // Verificar FEFO: debe usar el lote viejo primero, luego el medio
    // 4 kg necesarios: 5 kg del viejo (queda 1), luego nada del medio ni nuevo
    expect($batchOld->fresh()->quantity)->toBe(1.0) // 5 - 4 = 1
        ->and($batchMid->fresh()->quantity)->toBe(10.0) // Sin tocar
        ->and($batchNew->fresh()->quantity)->toBe(20.0); // Sin tocar
});

test('stock reduction handles insufficient batch stock gracefully', function () {
    // Crear ingrediente
    $ingredient = Ingredient::factory()->create([
        'name' => 'Lechuga',
        'measurement_unit' => 'unidades',
        'restaurant_id' => $this->restaurant->id,
    ]);
    
    // Crear lote con stock limitado
    $batch = Batch::factory()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 3, // Solo 3 unidades disponibles
        'expiration_date' => now()->addDays(5),
    ]);
    
    // Crear receta
    $recipe = Recipe::factory()->create([
        'name' => 'Burger con Lechuga',
        'instructions' => 'Agregar lechuga',
        'restaurant_id' => $this->restaurant->id,
    ]);
    
    $recipe->ingredients()->attach($ingredient->id, ['required_amount' => 1]);
    
    // Crear producto
    $product = Product::factory()->create([
        'name' => 'Burger Especial',
        'category_id' => $this->category->id,
        'recipe_id' => $recipe->id,
        'price' => 600,
    ]);
    
    // Crear orden que requiere más de lo disponible
    $order = Order::create([
        'restaurant_id' => $this->restaurant->id,
        'customer_id' => $this->customer->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->user->id,
        'status' => 'pending',
        'total' => 3000,
        'stock_deducted' => false,
    ]);
    
    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 5, // Requiere 5 lechugas pero solo hay 3
        'unit_price' => 600,
        'subtotal' => 3000,
    ]);
    
    // Disparar evento
    event(new OrderProcessing($order));
    
    // Verificar que se descontó hasta donde se pudo
    expect($batch->fresh()->quantity)->toBe(0.0) // Descuenta todo lo disponible
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});
