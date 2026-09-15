<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemporalProductFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeRestaurant(int $id): Restaurant
    {
        return Restaurant::factory()->create(['id' => $id]);
    }

    private function makeBatch(Restaurant $restaurant, float $quantity, ?string $expirationDays): IngredientBatch
    {
        $ingredient = Ingredient::query()->create([
            'name' => 'Ingrediente '.uniqid(),
            'measurement_unit' => 'unidades',
            'reorder_point' => 0,
            'min_stock' => 5,
            'restaurant_id' => $restaurant->id,
        ]);

        return IngredientBatch::query()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => $quantity,
            'expiration_date' => $expirationDays === null ? null : now()->{$expirationDays}(),
        ]);
    }

    private function makeTemporalProduct(Restaurant $restaurant, ?IngredientBatch $batch, bool $isAvailable = true): Product
    {
        return Product::factory()->create([
            'name' => 'Temporal '.uniqid(),
            'restaurant_id' => $restaurant->id,
            'price' => 1500,
            'stock' => 10,
            'is_available' => $isAvailable,
            'is_temporal' => true,
            'critical_ingredient_id' => $batch?->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // shouldBeAvailable — regla de aparición/desaparición
    // ─────────────────────────────────────────────────────────────

    public function test_temporal_aparece_cuando_lote_critico_esta_sano(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $batch = $this->makeBatch($restaurant, 100, 'addDays'); // +1 día por defecto

        $product = $this->makeTemporalProduct($restaurant, $batch);

        $this->assertTrue($product->shouldBeAvailable());
    }

    public function test_temporal_desaparece_cuando_lote_critico_vencio(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $batch = $this->makeBatch($restaurant, 100, 'subDays'); // vencido

        $product = $this->makeTemporalProduct($restaurant, $batch);

        $this->assertFalse($product->shouldBeAvailable());
    }

    public function test_temporal_desaparece_cuando_lote_critico_se_agoto(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $batch = $this->makeBatch($restaurant, 0, 'addDays'); // sin stock

        $product = $this->makeTemporalProduct($restaurant, $batch);

        $this->assertFalse($product->shouldBeAvailable());
    }

    public function test_temporal_sin_lote_critico_queda_disponible(): void
    {
        $restaurant = $this->makeRestaurant(1);

        $product = $this->makeTemporalProduct($restaurant, null);

        $this->assertTrue($product->shouldBeAvailable());
    }

    public function test_producto_no_temporal_siempre_disponible_segun_flag(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $batch = $this->makeBatch($restaurant, 100, 'subDays'); // vencido, pero no aplica

        $product = Product::factory()->create([
            'name' => 'Normal',
            'restaurant_id' => $restaurant->id,
            'is_available' => false,
            'is_temporal' => false,
            'critical_ingredient_id' => null,
        ]);

        $this->assertTrue($product->shouldBeAvailable());
        // La disponibilidad de un producto normal NO la toca el sync.
        $this->assertFalse($product->syncTemporalAvailability());
        $this->assertFalse($product->is_available);
    }

    // ─────────────────────────────────────────────────────────────
    // Sync (comando) — aplica la regla a la DB
    // ─────────────────────────────────────────────────────────────

    public function test_comando_oculta_temporal_con_lote_vencido(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $batch = $this->makeBatch($restaurant, 100, 'subDays');

        $product = $this->makeTemporalProduct($restaurant, $batch, true); // is_available=true

        $this->artisan('products:sync-temporals')->assertSuccessful();

        $this->assertFalse($product->fresh()->is_available);
    }

    public function test_comando_publica_temporal_antes_oculto_con_lote_sano(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $batch = $this->makeBatch($restaurant, 50, 'addDays');

        $product = $this->makeTemporalProduct($restaurant, $batch, false); // is_available=false

        $this->artisan('products:sync-temporals')->assertSuccessful();

        $this->assertTrue($product->fresh()->is_available);
    }

    public function test_comando_no_toca_temporales_con_lote_estable(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $batch = $this->makeBatch($restaurant, 50, 'addDays');

        $product = $this->makeTemporalProduct($restaurant, $batch, true);

        $this->artisan('products:sync-temporals')->assertSuccessful();

        $this->assertTrue($product->fresh()->is_available);
    }

    public function test_comando_ignora_restaurantes_de_prueba(): void
    {
        $this->makeRestaurant(1);
        $r2 = $this->makeRestaurant(2);
        $batch = $this->makeBatch($r2, 100, 'subDays');

        // Temporal del restaurante 2 con lote vencido: NO lo toca el comando.
        $product = $this->makeTemporalProduct($r2, $batch, true);

        $this->artisan('products:sync-temporals')->assertSuccessful();

        $this->assertTrue($product->fresh()->is_available);
    }
}