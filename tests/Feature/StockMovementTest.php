<?php

namespace Tests\Feature;

use App\Filament\Resources\IngredientResource\Pages\ListIngredients;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\StockMovement;
use App\Models\Table;
use App\Models\User;
use App\Services\StockDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * TAREA 2.2 (CU-003.1) — historial de movimientos de stock + mermas.
 * Testing Strategy B: happy path, seguridad de la acción merma y edge cases.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->service = new StockDeductionService();
});

function stockUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function stockIngredient(float $quantity = 10): Ingredient
{
    return Ingredient::create([
        'name' => 'Ingrediente '.uniqid(),
        'measurement_unit' => 'unidades',
        'reorder_point' => 0,
        'min_stock' => 0,
        'restaurant_id' => 1,
    ]);
}

function stockRecipeProduct(string $name, float $price, Ingredient $ingredient, float $requiredAmount): Product
{
    $recipe = \App\Models\Recipe::create(['name' => 'Receta '.uniqid(), 'instructions' => 'Preparar']);
    $recipe->ingredients()->attach($ingredient->id, ['required_amount' => $requiredAmount]);

    return Product::factory()->create([
        'name' => $name,
        'price' => $price,
        'is_available' => true,
        'category_id' => \App\Models\Category::firstOrCreate(['name' => 'Hamburguesas'])->id,
        'restaurant_id' => 1,
        'recipe_id' => $recipe->id,
        'stock' => 0,
    ]);
}

function stockOrder(User $user, Product $product, int $qty, float $price): Order
{
    $table = Table::create([
        'number' => '77',
        'capacity' => 4,
        'location' => 'Salón',
        'status' => 'occupied',
        'waiter_id' => $user->id,
        'restaurant_id' => 1,
    ]);

    $order = Order::create([
        'restaurant_id' => 1,
        'table_id' => $table->id,
        'waiter_id' => $user->id,
        'status' => 'pending',
        'type' => 'salon',
        'stock_deducted' => false,
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'price' => $price,
    ]);

    return $order;
}

// ─────────────────────────────────────────────────────────────
// 1) HAPPY PATH — vender con receta deja `salida` con before/after; el alta
//    de lote deja `entrada`; la merma con motivo descuenta y queda registrada.
// ─────────────────────────────────────────────────────────────

it('registra la salida FEFO con stock_before/stock_after correctos al vender', function () {
    $mozo = stockUser('Mozo');
    $ingredient = stockIngredient(10);

    IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 10,
        'expiration_date' => now()->addDays(5),
    ]);

    $product = stockRecipeProduct('Burger '.uniqid(), 600, $ingredient, 2);
    $order = stockOrder($mozo, $product, 3, 600); // requiere 6 unidades

    $this->service->deductForOrder($order);

    $movement = StockMovement::where('type', StockMovement::TYPE_SALIDA)->first();

    expect($movement)->not->toBeNull()
        ->and($movement->ingredient_id)->toBe($ingredient->id)
        ->and((float) $movement->quantity)->toBe(6.0)
        ->and((float) $movement->stock_before)->toBe(10.0)
        ->and((float) $movement->stock_after)->toBe(4.0)
        ->and($movement->reason)->toContain('Pedido #'.$order->id)
        ->and($movement->reference_type)->toBe(Order::class)
        ->and($movement->reference_id)->toBe($order->id);

    // El lote FEFO quedó descontado y NO hay `ajuste` fantasma del hook.
    expect((float) $ingredient->batches()->first()->quantity)->toBe(4.0)
        ->and(StockMovement::where('type', StockMovement::TYPE_AJUSTE)->count())->toBe(0);
});

it('registra la entrada al crear un lote con stock inicial', function () {
    $ingredient = stockIngredient();

    IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 25,
        'expiration_date' => now()->addDays(10),
    ]);

    $movement = StockMovement::where('type', StockMovement::TYPE_ENTRADA)->first();

    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(25.0)
        ->and((float) $movement->stock_before)->toBe(0.0)
        ->and((float) $movement->stock_after)->toBe(25.0)
        ->and($movement->reason)->toContain('Alta de lote');
});

it('registra merma con motivo y descuenta el stock', function () {
    $admin = stockUser('super_admin');
    $ingredient = stockIngredient(10);

    IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 10,
        'expiration_date' => now()->addDays(5),
    ]);

    $this->actingAs($admin);

    $this->service->registerMerma($ingredient, 4, 'Vencimiento de lote');

    $movement = StockMovement::where('type', StockMovement::TYPE_MERMA)->first();

    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(4.0)
        ->and((float) $movement->stock_before)->toBe(10.0)
        ->and((float) $movement->stock_after)->toBe(6.0)
        ->and($movement->reason)->toBe('Vencimiento de lote')
        ->and($movement->user_id)->toBe($admin->id);

    expect((float) $ingredient->batches()->first()->quantity)->toBe(6.0);
});

// ─────────────────────────────────────────────────────────────
// 2) SEGURIDAD — la acción "Registrar merma" es solo super_admin; un Mozo
//    con acceso al listado no la ve y la defensa en profundidad aborta 403.
// ─────────────────────────────────────────────────────────────

it('oculta la acción de merma a usuarios sin super_admin', function () {
    $ingredient = stockIngredient(5);
    IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 5,
        'expiration_date' => now()->addDays(5),
    ]);

    $mozo = stockUser('Mozo');
    $mozo->givePermissionTo(Permission::findOrCreate('view_any_ingredient', 'web'));

    Livewire::actingAs($mozo)
        ->test(ListIngredients::class)
        ->assertTableActionHidden('registrarMerma');
});

it('aborta con 403 si un usuario sin super_admin fuerza la acción de merma', function () {
    $ingredient = stockIngredient(5);
    IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 5,
        'expiration_date' => now()->addDays(5),
    ]);

    $mozo = stockUser('Mozo');
    $mozo->givePermissionTo(Permission::findOrCreate('view_any_ingredient', 'web'));

    // Se monta con un usuario que accede al listado y se obtiene la acción.
    $component = Livewire::actingAs($mozo)->test(ListIngredients::class);

    expect($component->instance()->getTable()->getAction('registrarMerma'))->not->toBeNull();

    $action = $component->instance()->getTable()->getAction('registrarMerma');
    $action->record($ingredient);

    // Ejecutarla con el Mozo (sin super_admin) → 403 (defensa en profundidad).
    $this->actingAs($mozo);
    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    $action->call(['record' => $ingredient, 'data' => ['quantity' => 2, 'reason' => 'Intento no autorizado']]);
});

it('niega 403 a Cocinero aunque tenga permiso de lectura (resource lo excluye)', function () {
    $cocinero = stockUser('Cocinero');
    // Un Cocinero con el permiso técnico tampoco accede: canViewAny del
    // resource excluye al rol (no ve el listado ni el historial).
    $cocinero->givePermissionTo(Permission::findOrCreate('view_any_ingredient', 'web'));

    Livewire::actingAs($cocinero)
        ->test(ListIngredients::class)
        ->assertStatus(403);
});

it('permite a super_admin registrar merma desde la acción del listado (happy path UI)', function () {
    $admin = stockUser('super_admin');
    $admin->givePermissionTo(Permission::findOrCreate('view_any_ingredient', 'web'));

    $ingredient = stockIngredient(10);

    IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 10,
        'expiration_date' => now()->addDays(5),
    ]);

    Livewire::actingAs($admin)
        ->test(ListIngredients::class)
        ->assertTableActionVisible('registrarMerma')
        ->callTableAction('registrarMerma', $ingredient, [
            'quantity' => 3,
            'reason' => 'Rotura en cocina',
        ])
        ->assertHasNoTableActionErrors();

    $movement = StockMovement::where('type', StockMovement::TYPE_MERMA)->first();

    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(3.0)
        ->and((float) $ingredient->batches()->first()->quantity)->toBe(7.0);
});

// ─────────────────────────────────────────────────────────────
// 3) EDGE — motivo vacío rechazado, cantidad > stock rechazada con error
//    claro, y venta sin ingredientes no genera movimiento fantasma.
// ─────────────────────────────────────────────────────────────

it('rechaza merma con motivo vacío', function () {
    $ingredient = stockIngredient(5);
    IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 5,
        'expiration_date' => now()->addDays(5),
    ]);

    expect(fn () => $this->service->registerMerma($ingredient, 2, '   '))
        ->toThrow(\InvalidArgumentException::class, 'obligatorio');

    expect(StockMovement::where('type', StockMovement::TYPE_MERMA)->count())->toBe(0);
});

it('rechaza merma con cantidad mayor al stock disponible con error claro', function () {
    $ingredient = stockIngredient(5);
    IngredientBatch::create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 5,
        'expiration_date' => now()->addDays(5),
    ]);

    expect(fn () => $this->service->registerMerma($ingredient, 8, 'Vencimiento'))
        ->toThrow(\DomainException::class, 'Stock disponible insuficiente');

    expect(StockMovement::where('type', StockMovement::TYPE_MERMA)->count())->toBe(0)
        ->and((float) $ingredient->batches()->first()->quantity)->toBe(5.0);
});

it('no crea movimientos fantasma para ventas sin ingredientes (producto directo)', function () {
    $mozo = stockUser('Mozo');

    $product = Product::factory()->create([
        'name' => 'Coca '.uniqid(),
        'price' => 200,
        'is_available' => true,
        'category_id' => \App\Models\Category::firstOrCreate(['name' => 'Bebidas'])->id,
        'restaurant_id' => 1,
        'recipe_id' => null,
        'stock' => 50,
    ]);

    $order = stockOrder($mozo, $product, 2, 200);

    $this->service->deductForOrder($order);

    expect(StockMovement::count())->toBe(0)
        ->and((float) $product->fresh()->stock)->toBe(48.0)
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});

it('no crea movimientos fantasma para recetas sin lotes disponibles', function () {
    $mozo = stockUser('Mozo');
    $ingredient = stockIngredient(0); // sin lotes

    $product = stockRecipeProduct('Sin stock '.uniqid(), 500, $ingredient, 2);
    $order = stockOrder($mozo, $product, 2, 500);

    $this->service->deductForOrder($order);

    // La comanda avanza (marca stock_deducted) pero no hay movimiento.
    expect(StockMovement::count())->toBe(0)
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});