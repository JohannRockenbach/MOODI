<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRecipeAndCategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function makeRestaurant(int $id): Restaurant
    {
        return Restaurant::factory()->create(['id' => $id]);
    }

    private function makeIngredient(Restaurant $restaurant, string $name): Ingredient
    {
        return Ingredient::query()->create([
            'name' => $name,
            'measurement_unit' => 'unidades',
            'reorder_point' => 0,
            'min_stock' => 5,
            'restaurant_id' => $restaurant->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // VÍNCULO PRODUCTO ↔ RECETA: realStock aplica al asignar recipe_id
    // ─────────────────────────────────────────────────────────────

    public function test_producto_con_receta_calcula_real_stock_desde_ingredientes(): void
    {
        $restaurant = $this->makeRestaurant(1);

        $pan = $this->makeIngredient($restaurant, 'Pan');
        $carne = $this->makeIngredient($restaurant, 'Carne');

        IngredientBatch::query()->create(['ingredient_id' => $pan->id, 'quantity' => 10, 'expiration_date' => now()->addDays(5)]);
        IngredientBatch::query()->create(['ingredient_id' => $carne->id, 'quantity' => 4, 'expiration_date' => now()->addDays(5)]);

        $recipe = Recipe::query()->create(['name' => 'Burger', 'instructions' => 'x']);
        $recipe->ingredients()->attach([
            $pan->id => ['required_amount' => 1],
            $carne->id => ['required_amount' => 1],
        ]);

        // Producto elaborado: stock directo 0, pero realStock = min(10/1, 4/1) = 4.
        $product = Product::factory()->create([
            'name' => 'Hamburguesa C/R',
            'restaurant_id' => $restaurant->id,
            'recipe_id' => $recipe->id,
            'stock' => 0,
            'min_stock' => 2,
        ]);

        $this->assertSame(4, $product->real_stock);
    }

    public function test_producto_sin_receta_usa_stock_directo(): void
    {
        $restaurant = $this->makeRestaurant(1);

        $product = Product::factory()->create([
            'name' => 'Coca-Cola',
            'restaurant_id' => $restaurant->id,
            'recipe_id' => null,
            'stock' => 12,
        ]);

        $this->assertSame(12, $product->real_stock);
    }

    // ─────────────────────────────────────────────────────────────
    // JERARQUÍA DE CATEGORÍAS: crear subcategoría + anti-ciclos
    // ─────────────────────────────────────────────────────────────

    public function test_crear_subcategoria_con_parent_id(): void
    {
        $padre = Category::query()->create(['name' => 'Comidas']);
        $hija = Category::query()->create(['name' => 'Hamburguesas', 'parent_id' => $padre->id]);

        $this->assertSame($padre->id, $hija->fresh()->parent_id);
        $this->assertTrue($padre->children()->whereKey($hija->id)->exists());
    }

    public function test_categoria_no_puede_ser_padre_de_si_misma(): void
    {
        $cat = Category::query()->create(['name' => 'Bebidas']);

        $this->expectException(\Exception::class);
        $cat->parent_id = $cat->id;
        $cat->save();
    }

    public function test_ciclo_cerrado_A_B_A_es_rechazado(): void
    {
        $a = Category::query()->create(['name' => 'A']);
        $b = Category::query()->create(['name' => 'B', 'parent_id' => $a->id]);

        // A → B (padre de A = B) crea el ciclo A→B→A.
        $a->parent_id = $b->id;

        $this->expectException(\Exception::class);
        $a->save();
    }

    public function test_cadena_valida_de_tres_niveles(): void
    {
        $raiz = Category::query()->create(['name' => 'Raíz']);
        $medio = Category::query()->create(['name' => 'Medio', 'parent_id' => $raiz->id]);
        $hoja = Category::query()->create(['name' => 'Hoja', 'parent_id' => $medio->id]);

        $this->assertSame($medio->id, $hoja->fresh()->parent_id);
        $this->assertSame($raiz->id, $medio->fresh()->parent_id);
    }
}