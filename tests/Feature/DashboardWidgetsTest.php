<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\Table;
use App\Models\Order;
use App\Models\Reservation;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\StockNotificationsWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Cliente;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private function makeRestaurant(int $id): Restaurant
    {
        return Restaurant::factory()->create(['id' => $id]);
    }

    private function makeIngredient(Restaurant $restaurant, string $name, float $minStock = 10): Ingredient
    {
        return Ingredient::query()->create([
            'name' => $name,
            'measurement_unit' => 'gramos',
            'reorder_point' => 0,
            'min_stock' => $minStock,
            'restaurant_id' => $restaurant->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // INGREDIENT::AVAILABLE_STOCK — lotes vencidos excluidos
    // ─────────────────────────────────────────────────────────────

    public function test_available_stock_excluye_lotes_vencidos(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $ingredient = $this->makeIngredient($restaurant, 'Queso');

        IngredientBatch::query()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
            'expiration_date' => now()->subDay(), // vencido
        ]);
        IngredientBatch::query()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'expiration_date' => now()->addDays(10), // sano
        ]);
        IngredientBatch::query()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 25,
            'expiration_date' => null, // sin fecha: disponible
        ]);

        $this->assertSame(75.0, $ingredient->availableStock());
        // total_stock (legacy) SÍ incluye vencidos: solo suma bruta.
        $this->assertSame(175.0, $ingredient->total_stock);
    }

    // ─────────────────────────────────────────────────────────────
    // PRODUCT::REAL_STOCK — no cuenta vencidos y usa cuello de botella
    // ─────────────────────────────────────────────────────────────

    public function test_real_stock_de_producto_con_receta_excluye_vencidos(): void
    {
        $restaurant = $this->makeRestaurant(1);

        $pan = $this->makeIngredient($restaurant, 'Pan');
        $carne = $this->makeIngredient($restaurant, 'Carne');

        // Pan: 10 unidades sanas + 90 vencidas → solo 10 disponibles.
        IngredientBatch::query()->create(['ingredient_id' => $pan->id, 'quantity' => 10, 'expiration_date' => now()->addDays(5)]);
        IngredientBatch::query()->create(['ingredient_id' => $pan->id, 'quantity' => 90, 'expiration_date' => now()->subDay()]);
        // Carne: 20 unidades sanas.
        IngredientBatch::query()->create(['ingredient_id' => $carne->id, 'quantity' => 20, 'expiration_date' => now()->addDays(5)]);

        $recipe = Recipe::query()->create(['name' => 'Burger', 'instructions' => 'x']);
        $recipe->ingredients()->attach([
            $pan->id => ['required_amount' => 1],
            $carne->id => ['required_amount' => 1],
        ]);

        $product = Product::factory()->create([
            'name' => 'Hamburguesa',
            'restaurant_id' => $restaurant->id,
            'recipe_id' => $recipe->id,
            'stock' => 0,
        ]);

        // Cuello de botella: pan 10/1 = 10, carne 20/1 = 20 → min = 10.
        $this->assertSame(10, $product->real_stock);
    }

    // ─────────────────────────────────────────────────────────────
    // LOW_STOCK_WIDGET — filtra restaurant y excluye vencidos
    // ─────────────────────────────────────────────────────────────

    public function test_low_stock_widget_ignora_ingredientes_de_otro_restaurante(): void
    {
        $r1 = $this->makeRestaurant(1);
        $r2 = $this->makeRestaurant(2);

        // Restaurante 2 tiene un ingrediente CRÍTICO con stock bajo.
        $ing2 = $this->makeIngredient($r2, 'Azafrán', 10);
        IngredientBatch::query()->create(['ingredient_id' => $ing2->id, 'quantity' => 1, 'expiration_date' => now()->addDays(5)]);

        $widget = new LowStockWidget();
        $data = $widget->getLowStockData();

        $this->assertNotContains('Azafrán', array_column($data, 'name'));
    }

    public function test_low_stock_widget_no_cuenta_lote_vencido_como_stock_disponible(): void
    {
        $restaurant = $this->makeRestaurant(1);

        $ing = $this->makeIngredient($restaurant, 'Tomate', 10);
        // Único lote: VENCIDO → disponible = 0 → stock bajo (aparece en el widget).
        IngredientBatch::query()->create(['ingredient_id' => $ing->id, 'quantity' => 500, 'expiration_date' => now()->subDay()]);

        $widget = new LowStockWidget();
        $data = $widget->getLowStockData();

        $row = collect($data)->firstWhere('name', 'Tomate');
        $this->assertNotNull($row);
        $this->assertSame(0.0, $row['current_stock']);
    }

    public function test_low_stock_widget_ignora_ingrediente_con_lotes_sanos_suficientes(): void
    {
        $restaurant = $this->makeRestaurant(1);

        $ing = $this->makeIngredient($restaurant, 'Papa', 10);
        IngredientBatch::query()->create(['ingredient_id' => $ing->id, 'quantity' => 100, 'expiration_date' => now()->addDays(5)]);

        $widget = new LowStockWidget();
        $data = $widget->getLowStockData();

        $this->assertNotContains('Papa', array_column($data, 'name'));
    }

    // ─────────────────────────────────────────────────────────────
    // STOCK_NOTIFICATIONS_WIDGET — misma política de vencidos y tenant
    // ─────────────────────────────────────────────────────────────

    public function test_stock_notifications_widget_ignora_otro_tenant_y_marca_vencido_como_critico(): void
    {
        $r1 = $this->makeRestaurant(1);
        $r2 = $this->makeRestaurant(2);

        // R1: lote vencido con stock alto → NO es stock disponible: queda crítico en 0.
        $ing1 = $this->makeIngredient($r1, 'Cebolla', 10);
        IngredientBatch::query()->create(['ingredient_id' => $ing1->id, 'quantity' => 999, 'expiration_date' => now()->subDay()]);

        // R2: crítico real → NO debe aparecer (cross-tenant).
        $ing2 = $this->makeIngredient($r2, 'Panceta', 10);
        IngredientBatch::query()->create(['ingredient_id' => $ing2->id, 'quantity' => 1, 'expiration_date' => now()->addDays(5)]);

        $widget = new StockNotificationsWidget();
        $items = $widget->getCriticalItems();

        $names = array_column($items, 'name');
        $this->assertNotContains('Panceta', $names); // cross-tenant fuera

        // La Cebolla (stock sano 0) sí aparece, marcada crítica en 0.
        $row = collect($items)->firstWhere('name', 'Cebolla');
        $this->assertNotNull($row);
        $this->assertSame(0.0, $row['current_stock']);
        $this->assertSame('critical', $row['severity']);
    }

    public function test_stock_notifications_widget_marca_critico_solo_con_stock_sano_bajo(): void
    {
        $restaurant = $this->makeRestaurant(1);

        $ing = $this->makeIngredient($restaurant, 'Lechuga', 10);
        IngredientBatch::query()->create(['ingredient_id' => $ing->id, 'quantity' => 3, 'expiration_date' => now()->addDays(5)]);

        $widget = new StockNotificationsWidget();
        $items = $widget->getCriticalItems();

        $row = collect($items)->firstWhere('name', 'Lechuga');
        $this->assertNotNull($row);
        $this->assertSame(3.0, $row['current_stock']);
        $this->assertSame('warning', $row['severity']);
    }
}