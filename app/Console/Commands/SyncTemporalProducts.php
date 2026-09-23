<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class SyncTemporalProducts extends Command
{
    protected $signature = 'products:sync-temporals';

    protected $description = 'Publica/oculta productos temporales según el estado de su lote crítico (anti-desperdicio)';

    public function handle(): int
    {
        $products = Product::query()
            ->where('restaurant_id', 1)
            ->where('is_temporal', true)
            ->with('criticalIngredient')
            ->get();

        if ($products->isEmpty()) {
            $this->info('✅ No hay productos temporales.');

            return Command::SUCCESS;
        }

        $appeared = 0;
        $disappeared = 0;

        foreach ($products as $product) {
            if ($product->syncTemporalAvailability()) {
                if ($product->is_available) {
                    $appeared++;
                    $this->info("   ✨ Aparece: {$product->name}");
                } else {
                    $disappeared++;
                    $this->info("   🕳️  Desaparece: {$product->name}");
                }
            }
        }

        $this->info("✅ Productos temporales sincronizados ({$appeared} aparecieron, {$disappeared} desaparecieron).");

        return Command::SUCCESS;
    }
}