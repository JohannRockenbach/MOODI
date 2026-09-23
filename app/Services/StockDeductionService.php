<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Descuento de stock FEFO (First Expired, First Out) para un pedido.
 *
 * Centraliza la lógica que antes vivía en UpdateStockListener para que
 * CUALQUIER flujo de cobro la reutilice sin duplicarla:
 * - UpdateStockListener (transición a 'processing').
 * - TableMap::cobrarMesa() (cobro por Mapa).
 * - HasCobroRapido (modal rápido de cobro en la página).
 *
 * IDEMPOTENTE: si $order->stock_deducted ya es true, no hace nada (el
 * pedido ya consumió su stock y no se vuelve a descontar).
 *
 * CASE 1 - Producto sin receta: decrement directo sobre products.stock
 *          (con lockForUpdate dentro de la transacción).
 * CASE 2 - Producto con receta: decrement por lote de ingredientes
 *          (quantity > 0, no vencidos, orderBy expiration_date asc + lock).
 */
class StockDeductionService
{
    /**
     * Descuenta el stock del pedido (FEFO) si todavía no fue descontado.
     * Corre dentro de su propia transacción; si se invoca dentro de otra
     * transacción Laravel usa savepoints (anida sin romper la externa).
     */
    public function deductForOrder(Order $order): void
    {
        $order->loadMissing('orderProducts.product.recipe.ingredients.batches');

        if ($order->stock_deducted) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->orderProducts as $item) {
                // CASE 1: Direct stock (no recipe)
                if (! $item->product->recipe_id) {
                    $required = (float) $item->quantity;

                    // Lock amplía el fix: evita carreras entre dos cobros
                    // simultáneos sobre el mismo producto sin receta.
                    /** @var Product|null $product */
                    $product = $item->product()->lockForUpdate()->first();

                    if (! $product) {
                        continue;
                    }

                    $currentStock = (float) $product->stock;

                    if ($currentStock < $required) {
                        Log::warning('Insufficient stock for product without recipe', [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'required' => $required,
                            'available' => $currentStock,
                        ]);
                        continue;
                    }

                    $product->decrement('stock', $required);
                    continue;
                }

                // CASE 2: Product with recipe (FEFO on ingredient batches)
                foreach ($item->product->recipe->ingredients as $ingredient) {
                    $requiredAmount = (float) $ingredient->pivot->required_amount * (float) $item->quantity;

                    // Get valid batches: with stock, not expired, ordered by expiration (FEFO)
                    $batches = $ingredient->batches()
                        ->where('quantity', '>', 0)
                        ->where(function ($query) {
                            $query->whereNull('expiration_date')
                                  ->orWhere('expiration_date', '>=', now()->toDateString());
                        })
                        ->orderBy('expiration_date', 'asc')
                        ->lockForUpdate()
                        ->get();

                    if ($batches->isEmpty()) {
                        Log::warning('No valid batches for ingredient', [
                            'ingredient_id' => $ingredient->id,
                            'ingredient_name' => $ingredient->name,
                            'required_amount' => $requiredAmount,
                        ]);
                        continue;
                    }

                    foreach ($batches as $batch) {
                        if ($requiredAmount <= 0) {
                            break;
                        }

                        $amountToDecrement = min($requiredAmount, (float) $batch->quantity);
                        $batch->decrement('quantity', $amountToDecrement);
                        $requiredAmount -= $amountToDecrement;
                    }

                    if ($requiredAmount > 0) {
                        Log::warning('Insufficient batch stock for ingredient', [
                            'ingredient_id' => $ingredient->id,
                            'ingredient_name' => $ingredient->name,
                            'remaining' => $requiredAmount,
                        ]);
                    }
                }
            }

            $order->stock_deducted = true;
            $order->saveQuietly();
        });
    }
}