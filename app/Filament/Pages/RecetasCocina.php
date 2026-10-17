<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Product;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * RecetasCocina — recetas de los productos elaborados para la cocina
 * (estilo Stitch, mismo clima que el KDS). El Cocinero selecciona un
 * producto con receta y ve ingredientes (con cantidades y unidad),
 * instrucciones y tiempo de preparación.
 *
 * Acceso: super_admin y Cocinero (cocina = super_admin/Cocinero). El resto
 * de roles NO entra: su lectura del catálogo vive en los resources.
 *
 * Navegación: esta página registra TAMBIÉN el item "Cocina" (KDS) para el
 * mismo conjunto, porque OrderResource tiene shouldRegisterNavigation =
 * false (Filament v3 no registra los items de un resource con esa bandera,
 * por lo que su getNavigationItems() nunca se invoca). Así el Cocinero
 * sigue viendo "Cocina" junto a "Recetas", sin depender de páginas del
 * salón (GestionPedidos) a las que ya no accede.
 */
class RecetasCocina extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static string $view = 'filament.pages.recetas-cocina';

    /**
     * Pantalla de cocina a ancho completo, igual que el KDS / TPV.
     */
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    protected static ?string $title = 'Recetas';

    protected static ?string $navigationLabel = 'Recetas';

    protected static ?string $navigationGroup = 'Operaciones del Salón';

    protected static ?int $navigationSort = 3;

    /**
     * Producto seleccionado (id) para mostrar su receta. null → grilla.
     */
    public ?int $selectedProductId = null;

    /**
     * Acceso: super_admin y Cocinero — el mismo conjunto que el KDS Cocina.
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->hasAnyRole(['super_admin', 'Cocinero']);
    }

    /**
     * Items de navegación: "Recetas" (esta página) + "Cocina" (KDS) para
     * super_admin/Cocinero. Ver docblock de la clase: el resource OrderResource
     * tiene shouldRegisterNavigation = false, así que este es el punto que
     * garantiza el item "Cocina" en el menú.
     */
    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();

        $user = Auth::user();

        if ($user && $user->hasAnyRole(['super_admin', 'Cocinero'])) {
            $items[] = NavigationItem::make('Cocina')
                ->icon('heroicon-o-fire')
                ->group('Operaciones del Salón')
                ->sort(1)
                ->url(OrderResource::getUrl('kitchen'));
        }

        return $items;
    }

    /**
     * Productos con receta disponibles para seleccionar (solo lectura).
     * Mismos criterios que el KDS: restaurant_id 1, is_available y receta
     * cargada. Orden alfabético para un selector estable.
     *
     * @return array<int, array{id: int, name: string, preparation_time_minutes: int|null, ingredients_count: int}>
     */
    #[Computed]
    public function availableRecipesProducts(): array
    {
        return Product::query()
            ->where('restaurant_id', 1)
            ->whereNotNull('recipe_id')
            ->where('is_available', true)
            ->with(['recipe' => fn ($query) => $query->with('ingredients')])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'preparation_time_minutes' => $product->preparation_time_minutes,
                'ingredients_count' => (int) ($product->recipe?->ingredients->count() ?? 0),
            ])
            ->all();
    }

    /**
     * Receta del producto seleccionado, enriquecida para el blade:
     * producto (nombre + tiempo), receta (nombre + instrucciones) e
     * ingredientes (nombre + required_amount + unidad real del modelo
     * Ingredient: measurement_unit).
     *
     * @return array{id: int, name: string, preparation_time_minutes: int|null, recipe_name: string, instructions: string, ingredients: array<int, array{name: string, required_amount: float, unit: string}>, has_ingredients: bool}|null
     */
    #[Computed]
    public function selectedProduct(): ?array
    {
        if (! $this->selectedProductId) {
            return null;
        }

        $product = Product::query()
            ->where('restaurant_id', 1)
            ->whereNotNull('recipe_id')
            ->where('is_available', true)
            ->with(['recipe' => fn ($query) => $query->with('ingredients')])
            ->find($this->selectedProductId);

        // Producto inexistente, de otro restaurante o sin receta → no rompe.
        if (! $product || ! $product->recipe) {
            return null;
        }

        $ingredients = $product->recipe->ingredients
            ->map(fn ($ingredient): array => [
                'name' => (string) $ingredient->name,
                'required_amount' => (float) ($ingredient->pivot->required_amount ?? 0),
                'unit' => (string) ($ingredient->measurement_unit ?? ''),
            ])
            ->values()
            ->all();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'preparation_time_minutes' => $product->preparation_time_minutes,
            'recipe_name' => (string) $product->recipe->name,
            'instructions' => (string) $product->recipe->instructions,
            'ingredients' => $ingredients,
            'has_ingredients' => count($ingredients) > 0,
        ];
    }

    /**
     * Seleccionar un producto de la grilla (wire:click). Un id inexistente,
     * de otro restaurante o sin receta simplemente no selecciona nada.
     */
    public function selectRecipe(int $productId): void
    {
        $valid = Product::query()
            ->where('restaurant_id', 1)
            ->whereNotNull('recipe_id')
            ->where('is_available', true)
            ->whereKey($productId)
            ->exists();

        $this->selectedProductId = $valid ? $productId : null;
    }

    /**
     * Volver a la grilla de productos (wire:click).
     */
    public function clearRecipe(): void
    {
        $this->selectedProductId = null;
    }
}
