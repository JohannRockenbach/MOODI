<?php

namespace App\Filament\Widgets;

use App\Models\Ingredient;
use App\Models\Product;
use Filament\Widgets\Widget;

class StockNotificationsWidget extends Widget
{
    protected static ?int $sort = 1; // Aparecer primero en el dashboard
    protected int | string | array $columnSpan = 'full';
    
    // Actualizar cada 30 segundos
    protected static ?string $pollingInterval = '30s';

    protected static string $view = 'filament.widgets.stock-notifications-widget';

    public function getCriticalItems(): array
    {
        // Obtener todos los ingredientes con sus lotes
        $ingredients = Ingredient::query()
            ->with(['restaurant', 'batches'])
            ->where('restaurant_id', 1)
            ->get()
            ->filter(function ($ingredient) {
                // Stock disponible: lotes NO vencidos (colección ya cargada, sin N+1)
                $totalStock = $ingredient->availableStock();
                // Filtrar solo los que tienen stock crítico (0) o bajo (<= 50% min_stock)
                return $totalStock <= 0 || $totalStock <= $ingredient->min_stock * 0.5;
            })
            ->map(function ($ingredient) {
                $totalStock = $ingredient->availableStock();
                return [
                    'name' => $ingredient->name,
                    'current_stock' => $totalStock,
                    'min_stock' => $ingredient->min_stock,
                    'type' => 'Ingrediente',
                    'measurement_unit' => $ingredient->measurement_unit,
                    'severity' => $totalStock <= 0 ? 'critical' : 'warning',
                ];
            });

        $products = Product::query()
            ->whereNull('recipe_id')
            ->where('restaurant_id', 1)
            ->where(function ($query) {
                $query->where('stock', '<=', 0)
                    ->orWhereRaw('stock <= min_stock * 0.5');
            })
            ->with('restaurant')
            ->get()
            ->map(function ($product) {
                return [
                    'name' => $product->name,
                    'current_stock' => $product->stock,
                    'min_stock' => $product->min_stock,
                    'type' => 'Producto',
                    'measurement_unit' => null,
                    'severity' => $product->stock <= 0 ? 'critical' : 'warning',
                ];
            });

        return $ingredients->merge($products)
            ->sortBy(function ($item) {
                return $item['severity'] === 'critical' ? 0 : 1;
            })
            ->values()
            ->toArray();
    }

    public function shouldShowNotification(): bool
    {
        return count($this->getCriticalItems()) > 0;
    }
}
