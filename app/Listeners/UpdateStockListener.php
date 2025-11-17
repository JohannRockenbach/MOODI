<?php

namespace App\Listeners;

use App\Events\OrderProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
        // Cargar todas las relaciones necesarias
        $order = $event->order->load('orderProducts.product.recipe.ingredients.batches');

        // Verificar si ya se descontó el stock para este pedido
        if ($order->stock_deducted) {
            return; // Ya se descontó, no hacer nada
        }

        // Iterar sobre cada producto del pedido
        foreach ($order->orderProducts as $item) {
            // CASO 1: Stock Directo (sin receta - ej. Coca-Cola)
            if (!$item->product->recipe_id) {
                // Simplemente descontar del stock del producto
                $item->product->decrement('stock', $item->quantity);
                continue;
            }

            // CASO 2: Producto con Receta (ej. Hamburguesa)
            if ($item->product->recipe_id) {
                // Iterar sobre cada ingrediente de la receta
                foreach ($item->product->recipe->ingredients as $ingredient) {
                    // Calcular cantidad total a descontar
                    // (cantidad en receta * cantidad de productos pedidos)
                    $requiredAmount = $ingredient->pivot->required_amount * $item->quantity;

                    // Obtener lotes del ingrediente ordenados por fecha de vencimiento (FEFO)
                    // Solo lotes con stock disponible
                    $batches = $ingredient->batches()
                        ->where('quantity', '>', 0)
                        ->orderBy('expiration_date', 'asc')
                        ->get();

                    if ($batches->isEmpty()) {
                        continue;
                    }

                    // Descontar usando lógica FEFO
                    foreach ($batches as $batch) {
                        if ($requiredAmount <= 0) {
                            break; // Ya se descontó todo lo necesario
                        }

                        // Descontar lo que se pueda de este lote
                        $amountToDecrement = min($requiredAmount, $batch->quantity);
                        $batch->decrement('quantity', $amountToDecrement);
                        $requiredAmount -= $amountToDecrement;
                    }
                }
            }
        }

        // Marcar que el stock ya fue descontado
        // Usamos saveQuietly() para no disparar el Observer de nuevo
        $order->stock_deducted = true;
        $order->saveQuietly();
    }
}
