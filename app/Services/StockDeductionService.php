<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
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
 *
 * HISTORIAL DE MOVIMIENTOS (TAREA 2.2 / CU-003.1): cada descuento FEFO
 * registra una fila `salida` por ingrediente con stock_before/stock_after y
 * referencia al Order. Los decrementos de lote se hacen con
 * IngredientBatch::withoutEvents() para que el hook `updated` del lote NO
 * escriba un `ajuste` fantasma además de la `salida`.
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
                $product = $item->product;

                // Defensa: el producto pudo ser soft-eliminado o el pedido ser
                // legacy con datos incompletos (DB de prueba vacía). El pedido
                // NO debe bloquearse: se loggea y la comanda avanza igual.
                if (! $product) {
                    Log::warning('Product removed for order item, stock deduction skipped', [
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                    ]);
                    continue;
                }

                // CASE 1: Direct stock (no recipe)
                if (! $product->recipe_id) {
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
                $recipe = $product->recipe;

                if (! $recipe) {
                    Log::warning('Recipe not found for product, stock deduction skipped', [
                        'product_id' => $product->id,
                        'recipe_id' => $product->recipe_id,
                    ]);
                    continue;
                }

                foreach ($recipe->ingredients as $ingredient) {
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

                    // Historial: stock antes = suma de los lotes válidos.
                    $stockBefore = (float) $batches->sum('quantity');
                    $totalDecremented = 0.0;

                    foreach ($batches as $batch) {
                        if ($requiredAmount <= 0) {
                            break;
                        }

                        $amountToDecrement = min($requiredAmount, (float) $batch->quantity);

                        // withoutEvents: el historial de este descuento lo
                        // registra el servicio (fila `salida`), no el hook
                        // `updated` del lote (que escribiría un `ajuste`).
                        IngredientBatch::withoutEvents(fn () => $batch->decrement('quantity', $amountToDecrement));

                        $requiredAmount -= $amountToDecrement;
                        $totalDecremented += $amountToDecrement;
                    }

                    if ($requiredAmount > 0) {
                        Log::warning('Insufficient batch stock for ingredient', [
                            'ingredient_id' => $ingredient->id,
                            'ingredient_name' => $ingredient->name,
                            'remaining' => $requiredAmount,
                        ]);
                    }

                    if ($totalDecremented > 0) {
                        $this->recordSaleMovement($ingredient, $order, $stockBefore, $totalDecremented);
                    }
                }
            }

            $order->stock_deducted = true;
            $order->saveQuietly();
        });
    }

    /**
     * Registrar la fila `salida` de un descuento FEFO por venta. La referencia
     * concreta en el punto de descuento es el Order (la Sale puede no existir
     * todavía: se crea después del descuento en el flujo de cobro).
     */
    private function recordSaleMovement(Ingredient $ingredient, Order $order, float $stockBefore, float $totalDecremented): void
    {
        try {
            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'type' => StockMovement::TYPE_SALIDA,
                'quantity' => round($totalDecremented, 2),
                'stock_before' => round($stockBefore, 2),
                'stock_after' => round(max(0, $stockBefore - $totalDecremented), 2),
                'reason' => 'Venta · Pedido #'.$order->id,
                'user_id' => $order->waiter_id ?: Auth::id(),
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('StockDeductionService: no se pudo registrar la salida de stock', [
                'order_id' => $order->id,
                'ingredient_id' => $ingredient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registrar una MERMA (TAREA 2.2 / CU-003.1): descuenta stock FEFO del
     * ingrediente con motivo OBLIGATORIO y rechaza si la cantidad supera el
     * stock disponible (regla del DR: salida mayor al stock = error claro).
     *
     * Corre en transacción propia con lockForUpdate sobre el ingrediente y
     * los lotes (misma lógica de actualización que deductForOrder).
     *
     * @throws \InvalidArgumentException motivo vacío o cantidad <= 0
     * @throws \DomainException cantidad mayor al stock disponible
     */
    public function registerMerma(Ingredient $ingredient, float $quantity, string $reason): StockMovement
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('El motivo de la merma es obligatorio.');
        }

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La cantidad de merma debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($ingredient, $quantity, $reason): StockMovement {
            /** @var Ingredient $locked */
            $locked = Ingredient::query()
                ->whereKey($ingredient->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $available = $locked->availableStock();

            // Regla del DR/CU-003.1: la merma no puede superar el stock disponible.
            if ($quantity > $available + 0.001) {
                throw new \DomainException(
                    'Stock disponible insuficiente para la merma (disponible: '.number_format($available, 2, ',', '.').').'
                );
            }

            $batches = $locked->batches()
                ->where('quantity', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('expiration_date')
                          ->orWhere('expiration_date', '>=', now()->toDateString());
                })
                ->orderBy('expiration_date', 'asc')
                ->lockForUpdate()
                ->get();

            $remaining = $quantity;
            $decremented = 0.0;

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (float) $batch->quantity);
                IngredientBatch::withoutEvents(fn () => $batch->decrement('quantity', $take));
                $remaining -= $take;
                $decremented += $take;
            }

            if ($decremented <= 0) {
                throw new \DomainException('No hay stock válido para registrar la merma.');
            }

            $stockAfter = max(0, $available - $decremented);

            return StockMovement::create([
                'ingredient_id' => $locked->id,
                'type' => StockMovement::TYPE_MERMA,
                'quantity' => round($decremented, 2),
                'stock_before' => round($available, 2),
                'stock_after' => round($stockAfter, 2),
                'reason' => $reason,
                'user_id' => Auth::id(),
                'created_at' => now(),
            ]);
        });
    }
}