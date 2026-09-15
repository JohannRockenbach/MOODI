<?php

namespace App\Listeners;

use App\Events\OrderProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateStockListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     * Descuenta el stock usando lógica FEFO (First Expired, First Out).
     */
    public function handle(OrderProcessing $event): void
    {
        $order = $event->order->load('orderProducts.product.recipe.ingredients.batches');

        if ($order->stock_deducted) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->orderProducts as $item) {
                // CASE 1: Direct stock (no recipe)
                if (!$item->product->recipe_id) {
                    $currentStock = (float) $item->product->stock;
                    $required = (float) $item->quantity;

                    if ($currentStock < $required) {
                        Log::warning('Insufficient stock for product without recipe', [
                            'product_id' => $item->product->id,
                            'product_name' => $item->product->name,
                            'required' => $required,
                            'available' => $currentStock,
                        ]);
                        continue;
                    }

                    $item->product->decrement('stock', $required);
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
