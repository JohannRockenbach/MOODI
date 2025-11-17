<?php

namespace App\Filament\Widgets;

use App\Models\Ingredient;
use App\Models\Product;
use Filament\Widgets\Widget;

class LowStockWidget extends Widget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    
    // Hacer el widget "poling" cada 60 segundos para actualizar datos
    protected static ?string $pollingInterval = '60s';

    protected static string $view = 'filament.widgets.low-stock-widget';

    public function getCriticalCount(): int
    {
        return count(array_filter($this->getLowStockData(), function ($item) {
            return $item['current_stock'] <= 0 || $item['difference'] <= -5;
        }));
    }

    public function getWarningCount(): int
    {
        return count($this->getLowStockData());
    }

    public function getLowStockData(): array
    {
        // Obtener ingredientes con stock bajo
        $ingredients = Ingredient::query()
            ->with(['restaurant', 'batches'])
            ->get()
            ->filter(function ($ingredient) {
                // Calcular stock total de lotes
                $totalStock = $ingredient->batches()->sum('quantity');
                // Filtrar solo los que tienen stock bajo
                return $totalStock <= $ingredient->min_stock;
            })
            ->map(function ($ingredient) {
                $totalStock = $ingredient->batches()->sum('quantity');
                return [
                    'id' => 'i_' . $ingredient->id,
                    'name' => $ingredient->name,
                    'current_stock' => $totalStock,
                    'min_stock' => $ingredient->min_stock,
                    'difference' => $totalStock - $ingredient->min_stock,
                    'type' => 'Ingrediente',
                    'measurement_unit' => $ingredient->measurement_unit,
                    'restaurant_name' => $ingredient->restaurant->name ?? 'N/A',
                    'model' => $ingredient,
                ];
            });

        // Obtener productos de venta directa (sin receta) con stock bajo
        $products = Product::query()
            ->whereNull('recipe_id') // Solo productos de venta directa
            ->whereColumn('stock', '<=', 'min_stock')
            ->with('restaurant')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => 'p_' . $product->id,
                    'name' => $product->name,
                    'current_stock' => $product->stock,
                    'min_stock' => $product->min_stock,
                    'difference' => $product->stock - $product->min_stock,
                    'type' => 'Producto',
                    'measurement_unit' => null,
                    'restaurant_name' => $product->restaurant->name ?? 'N/A',
                    'model' => $product,
                ];
            });

        // Combinar y ordenar por diferencia (más críticos primero)
        return $ingredients->merge($products)
            ->sortBy('difference')
            ->values()
            ->toArray();
    }
}
