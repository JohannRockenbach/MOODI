<?php

namespace Tests\Feature;

use App\Filament\Pages\RecetasCocina;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Restaurant;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * TAREA 2 — Página "Recetas" para cocina (super_admin/Cocinero).
 * El Cocinero selecciona un producto con receta y ve ingredientes (con
 * cantidades y unidad), instrucciones y tiempo de preparación.
 *
 * Testing Strategy B:
 * - happy path: el Cocinero entra a Recetas, selecciona un producto con
 *   receta y ve los ingredientes con sus cantidades/unidad + instrucciones.
 * - seguridad: Mozo/Cajero NO acceden (canAccess false + 403); con la
 *   navegación REAL del panel, el Cocinero ve SOLO "Cocina" + "Recetas"
 *   aunque su rol tenga permisos de catálogo (view_any_product/recipe/
 *   ingredient) — valida también el recorte de acceso de la TAREA 1.
 * - edge: producto sin receta no aparece; seleccionar un id inexistente no
 *   rompe; receta sin ingredientes muestra el aviso.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero', 'cliente'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function recetasUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

/**
 * Producto con receta completa (ingredientes con cantidades + unidad real
 * del modelo Ingredient: measurement_unit). Devuelve [product, ingredients].
 */
function recetasProductoCompleto(): array
{
    $recipe = Recipe::create([
        'name' => 'Receta Hamburguesa OHANA',
        'instructions' => "1. Tostar el pan.\n2. Cocinar la carne.\n3. Armar y servir.",
    ]);

    $pan = Ingredient::create([
        'name' => 'Pan de hamburguesa',
        'measurement_unit' => 'un',
        'restaurant_id' => 1,
    ]);
    $carne = Ingredient::create([
        'name' => 'Carne picada',
        'measurement_unit' => 'kg',
        'restaurant_id' => 1,
    ]);

    $recipe->ingredients()->attach($pan->id, ['required_amount' => 1]);
    $recipe->ingredients()->attach($carne->id, ['required_amount' => 0.150]);

    $product = Product::factory()->forRestaurant(1)->available()->create([
        'name' => 'Hamburguesa OHANA',
        'recipe_id' => $recipe->id,
        'preparation_time_minutes' => 12,
        'price' => 6500,
    ]);

    return [$product, [$pan, $carne]];
}

// ─────────────────────────────────────────────────────────────
// 1) HAPPY PATH — el Cocinero entra, selecciona y ve la receta
// ─────────────────────────────────────────────────────────────

it('un Cocinero accede a Recetas, selecciona un producto y ve ingredientes, cantidades, unidad e instrucciones', function () {
    [$product] = recetasProductoCompleto();

    $cocinero = recetasUser('Cocinero');
    auth()->login($cocinero);

    expect(RecetasCocina::canAccess())->toBeTrue();

    Livewire::actingAs($cocinero)
        ->test(RecetasCocina::class)
        ->assertOk()
        // Grilla: el producto con receta aparece (con su tiempo).
        ->assertSee('Hamburguesa OHANA')
        ->assertSee('12 min')
        // Seleccionar → la receta se expone con ingredientes y cantidades.
        ->call('selectRecipe', $product->id)
        ->assertSet('selectedProductId', $product->id)
        ->assertSee('Receta Hamburguesa OHANA')
        ->assertSee('Pan de hamburguesa')
        ->assertSee('Carne picada')
        ->assertSee('kg')                   // unidad real (measurement_unit)
        ->assertSee('Tostar el pan.')       // instrucciones
        ->assertSee('Armar y servir.')
        ->assertSee('Cocina');              // el link del header al KDS
});

it('el producto seleccionado expone el detalle correcto en selectedProduct', function () {
    [$product] = recetasProductoCompleto();

    $cocinero = recetasUser('Cocinero');

    Livewire::actingAs($cocinero)
        ->test(RecetasCocina::class)
        ->call('selectRecipe', $product->id);

    // Leer el computed directamente para validar la estructura de datos.
    $component = Livewire::actingAs($cocinero)->test(RecetasCocina::class);
    $component->call('selectRecipe', $product->id);

    $selected = $component->instance()->selectedProduct;

    expect($selected)->not->toBeNull()
        ->and($selected['name'])->toBe('Hamburguesa OHANA')
        ->and($selected['preparation_time_minutes'])->toBe(12)
        ->and($selected['recipe_name'])->toBe('Receta Hamburguesa OHANA')
        ->and($selected['has_ingredients'])->toBeTrue()
        ->and($selected['ingredients'])->toHaveCount(2)
        ->and($selected['ingredients'][0]['name'])->toBe('Pan de hamburguesa')
        ->and((float) $selected['ingredients'][0]['required_amount'])->toBe(1.0)
        ->and($selected['ingredients'][0]['unit'])->toBe('un')
        ->and((float) $selected['ingredients'][1]['required_amount'])->toBe(0.150)
        ->and($selected['ingredients'][1]['unit'])->toBe('kg');
});

// ─────────────────────────────────────────────────────────────
// 2) SEGURIDAD — Mozo/Cajero NO acceden; menú del Cocinero SOLO Cocina+Recetas
// ─────────────────────────────────────────────────────────────

it('deniega el acceso a Mozo y Cajero (canAccess false y 403 al montar)', function () {
    foreach (['Mozo', 'Cajero'] as $role) {
        $user = recetasUser($role);

        expect(RecetasCocina::canAccess())->toBeFalse("{$role} no debería poder acceder a Recetas");

        Livewire::actingAs($user)
            ->test(RecetasCocina::class)
            ->assertStatus(403);
    }
});

it('el menú real del Cocinero contiene SOLO "Cocina" y "Recetas" aunque tenga permisos de catálogo', function () {
    // Reproducir la asignación real del StaffSeeder: el Cocinero tiene
    // permisos de lectura del catálogo. Aun así, los resources quedan
    // fuera de su menú (TAREA 1) y su navegación es SOLO Cocina + Recetas.
    $cocinero = recetasUser('Cocinero');

    foreach (['view_any_product', 'view_any_recipe', 'view_any_ingredient'] as $permission) {
        $cocinero->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    auth()->login($cocinero);

    $labels = collect(\Filament\Facades\Filament::getPanel('admin')->getNavigation())
        ->flatMap(fn ($group) => collect($group->getItems()))
        ->map(fn ($item) => $item->getLabel())
        ->unique()
        ->values()
        ->all();

    expect($labels)->toContain('Cocina')
        ->toContain('Recetas')
        ->and($labels)->toHaveCount(2);
});

// ─────────────────────────────────────────────────────────────
// 3) EDGE CASES
// ─────────────────────────────────────────────────────────────

it('no lista productos sin receta ni productos no disponibles', function () {
    recetasProductoCompleto();

    // Producto SIN receta (disponible) y producto CON receta pero no disponible.
    Product::factory()->forRestaurant(1)->available()->create([
        'name' => 'Coca Sin Receta',
        'recipe_id' => null,
    ]);

    $conRecetaInactivo = Recipe::create(['name' => 'Receta oculta', 'instructions' => 'X']);
    Product::factory()->forRestaurant(1)->unavailable()->create([
        'name' => 'Producto No Disponible',
        'recipe_id' => $conRecetaInactivo->id,
    ]);

    $cocinero = recetasUser('Cocinero');

    $component = Livewire::actingAs($cocinero)->test(RecetasCocina::class);

    $list = $component->instance()->availableRecipesProducts;

    expect($list)->toHaveCount(1)
        ->and($list[0]['name'])->toBe('Hamburguesa OHANA');
});

it('seleccionar un producto inexistente no rompe: vuelve al selector sin receta', function () {
    $cocinero = recetasUser('Cocinero');

    Livewire::actingAs($cocinero)
        ->test(RecetasCocina::class)
        ->call('selectRecipe', 999999)
        ->assertSet('selectedProductId', null)
        ->assertSee('Elegí un producto para ver su receta');
});

it('una receta sin ingredientes muestra el aviso y no rompe', function () {
    $recipe = Recipe::create([
        'name' => 'Receta vacía',
        'instructions' => 'No lleva ingredientes.',
    ]);

    $product = Product::factory()->forRestaurant(1)->available()->create([
        'name' => 'Té de Autor',
        'recipe_id' => $recipe->id,
    ]);

    $cocinero = recetasUser('Cocinero');

    Livewire::actingAs($cocinero)
        ->test(RecetasCocina::class)
        ->call('selectRecipe', $product->id)
        ->assertSee('Té de Autor')
        ->assertSee('No lleva ingredientes.')
        ->assertSee('Esta receta no tiene ingredientes cargados');
});

it('clearRecipe vuelve a la grilla de productos', function () {
    [$product] = recetasProductoCompleto();

    $cocinero = recetasUser('Cocinero');

    Livewire::actingAs($cocinero)
        ->test(RecetasCocina::class)
        ->call('selectRecipe', $product->id)
        ->assertSet('selectedProductId', $product->id)
        ->call('clearRecipe')
        ->assertSet('selectedProductId', null)
        ->assertSee('Elegí un producto para ver su receta');
});
