<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\KitchenDashboard;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresión del KDS Cocina: las acciones de las cards (EMPEZAR / LISTO /
 * ENTREGADO / CANCELAR) deben mover el pedido entre estados y refrescar
 * las columnas del dashboard.
 */
class KitchenDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Restaurant::factory()->create(['id' => 1]);

        foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero', 'cliente'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Categoría "Comida" (el KDS excluye Bebidas) + producto disponible.
        $category = \App\Models\Category::factory()->create(['name' => 'Comida']);
        $this->product = Product::factory()->forRestaurant(1)->available()->create([
            'category_id' => $category->id,
            'price' => 2500,
            'stock' => 20,
        ]);
    }

    private function makeCocinero(): User
    {
        $user = User::factory()->create([
            'email' => 'cocina-kds-' . uniqid() . '@test.com',
            'restaurant_id' => 1,
        ]);
        $user->assignRole('Cocinero');

        return $user;
    }

    private function makePendingOrder(): Order
    {
        $order = Order::factory()->forRestaurant(1)->pending()->create([
            'type' => 'para_llevar',
            'table_id' => null,
            'waiter_id' => User::factory()->create(['restaurant_id' => 1])->id,
        ]);
        $order->orderProducts()->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => $this->product->price,
        ]);

        return $order;
    }

    public function test_empezar_mueve_el_pedido_de_pendiente_a_en_preparacion(): void
    {
        $order = $this->makePendingOrder();

        $this->actingAs($this->makeCocinero());

        Livewire::test(KitchenDashboard::class)
            ->assertSee('#' . $order->id)          // aparece en Pendientes
            ->call('startProcessing', $order->id)   // ▶️ EMPEZAR
            ->assertHasNoErrors();                  // sin errores de Livewire

        $this->assertSame('processing', $order->fresh()->status, 'El pedido debería quedar en processing tras EMPEZAR.');
    }

    public function test_empezar_no_hace_nada_si_el_pedido_ya_no_esta_pendiente(): void
    {
        $order = $this->makePendingOrder();
        $order->update(['status' => 'processing']);

        $this->actingAs($this->makeCocinero());

        Livewire::test(KitchenDashboard::class)
            ->call('startProcessing', $order->id)
            ->assertHasNoErrors();

        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_marcar_listo_y_entregado_completan_el_flujo(): void
    {
        $order = $this->makePendingOrder();

        $this->actingAs($this->makeCocinero());

        Livewire::test(KitchenDashboard::class)
            ->call('startProcessing', $order->id)
            ->call('markAsReady', $order->id)
            ->call('markAsCompleted', $order->id)
            ->assertHasNoErrors();

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_empezar_funciona_con_producto_sin_receta_y_sin_ingredientes(): void
    {
        // Caso "DB vacía": producto simple (sin receta) — el descuento de stock
        // directo no debe romper la transición a processing.
        $order = Order::factory()->forRestaurant(1)->pending()->create([
            'type' => 'para_llevar',
            'table_id' => null,
            'waiter_id' => User::factory()->create(['restaurant_id' => 1])->id,
        ]);
        $order->orderProducts()->create([
            'product_id' => $this->product->id, // sin recipe_id
            'quantity' => 1,
            'price' => $this->product->price,
        ]);

        $this->actingAs($this->makeCocinero());

        Livewire::test(KitchenDashboard::class)
            ->call('startProcessing', $order->id)
            ->assertHasNoErrors();

        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_empezar_funciona_con_producto_eliminado_en_el_pedido(): void
    {
        // Caso "DB vacía"/datos de prueba viejos: el pedido apunta a un producto
        // soft-deleted (ya no se carga en la relación). El descuento no debe
        // romper la transición: se loggea y el pedido avanza a processing.
        $order = Order::factory()->forRestaurant(1)->pending()->create([
            'type' => 'para_llevar',
            'table_id' => null,
            'waiter_id' => User::factory()->create(['restaurant_id' => 1])->id,
        ]);
        $order->orderProducts()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $this->product->price,
        ]);

        // Soft delete del producto: la relación deja de cargarlo.
        $this->product->delete();

        $this->actingAs($this->makeCocinero());

        Livewire::test(KitchenDashboard::class)
            ->call('startProcessing', $order->id)
            ->assertHasNoErrors();

        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_empezar_funciona_con_receta_sin_insumos_disponibles(): void
    {
        // Hipótesis del dueño: "no inicia porque no hay ingredientes para
        // preparar". Producto CON receta y el ingrediente SIN lotes/batches:
        // el descuento FEFO no encuentra stock de insumos. NO debe romper la
        // transición: se loggea y el pedido avanza a processing.
        $recipe = \App\Models\Recipe::create([
            'name' => 'Receta Papas sin insumos',
            'instructions' => 'Preparar.',
        ]);
        $ingredient = \App\Models\Ingredient::create([
            'name' => 'Papa pelada',
            'current_stock' => 0,
            'measurement_unit' => 'kg',
            'reorder_point' => 0,
            'restaurant_id' => 1,
        ]);
        $recipe->ingredients()->attach($ingredient->id, ['required_amount' => 1]);

        $productWithRecipe = Product::factory()->forRestaurant(1)->available()->create([
            'category_id' => $this->product->category_id,
            'recipe_id' => $recipe->id,
            'price' => 3200,
            'stock' => 10,
        ]);

        $order = Order::factory()->forRestaurant(1)->pending()->create([
            'type' => 'para_llevar',
            'table_id' => null,
            'waiter_id' => User::factory()->create(['restaurant_id' => 1])->id,
        ]);
        $order->orderProducts()->create([
            'product_id' => $productWithRecipe->id,
            'quantity' => 1,
            'price' => $productWithRecipe->price,
        ]);

        $this->actingAs($this->makeCocinero());

        Livewire::test(KitchenDashboard::class)
            ->call('startProcessing', $order->id)
            ->assertHasNoErrors();

        $this->assertSame('processing', $order->fresh()->status);
        $this->assertTrue($order->fresh()->stock_deducted);
    }

    public function test_cancelar_mueve_el_pedido_a_cancelled(): void
    {
        $order = $this->makePendingOrder();

        $this->actingAs($this->makeCocinero());

        Livewire::test(KitchenDashboard::class)
            ->call('cancelOrder', $order->id)
            ->assertHasNoErrors();

        $this->assertSame('cancelled', $order->fresh()->status);
    }
}