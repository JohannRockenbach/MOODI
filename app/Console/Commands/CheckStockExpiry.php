<?php

namespace App\Console\Commands;

use App\Filament\Pages\SendCampaign;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Product;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class CheckStockExpiry extends Command
{
    protected $signature = 'stock:check-expiry';
    protected $description = 'Chef Inteligente: Detecta ingredientes por vencer y crea recetas temporales';

    public function handle(): int
    {
        $this->info('👨‍🍳 Chef Inteligente - Analizando inventario...');

        // Paso 1: Identificar Ingredientes Base
        $this->line('');
        $this->info('🔍 Buscando ingredientes base...');
        
        $panBase = Ingredient::where('name', 'like', '%Pan%')->first();
        $carneBase = Ingredient::where('name', 'like', '%Medallón%')->orWhere('name', 'like', '%Carne%')->first();
        
        if (!$panBase || !$carneBase) {
            $this->error('❌ No se encontraron ingredientes base (Pan y Carne).');
            return Command::FAILURE;
        }
        
        $this->info("   ✅ Pan base: {$panBase->name}");
        $this->info("   ✅ Carne base: {$carneBase->name}");

        // Paso 2: Detectar Ingredientes en Riesgo
        $this->line('');
        $this->info('⚠️ Detectando ingredientes críticos (≤ 5 días)...');
        
        $ignoredIngredients = [
            'Harina', 'Levadura', 'Sal', 'Azúcar', 'Agua', 'Aceite',
            'Papas Congeladas', 'Aceite de Oliva', 'Vinagre', 'Pimienta',
            'Huevo', 'Huevos', $panBase->name, $carneBase->name,
        ];

        $expiringBatches = IngredientBatch::where('quantity', '>', 0)
            ->where('expiration_date', '<=', now()->addDays(5))
            ->where('expiration_date', '>=', now())
            ->whereHas('ingredient', fn($q) => $q->whereNotIn('name', $ignoredIngredients))
            ->with('ingredient')
            ->get();

        if ($expiringBatches->isEmpty()) {
            $this->info('✅ No hay ingredientes en riesgo.');
            return Command::SUCCESS;
        }

        $topRisks = $expiringBatches->groupBy('ingredient_id')
            ->map(function ($batches) {
                $ingredient = $batches->first()->ingredient;
                return [
                    'ingredient' => $ingredient,
                    'total_quantity' => $batches->sum('quantity'),
                    'unit_cost' => $ingredient->unit_cost ?? 0,
                ];
            })
            ->sortByDesc('total_quantity')
            ->take(7);

        $this->warn("   🎯 {$topRisks->count()} ingrediente(s) crítico(s):");
        foreach ($topRisks as $risk) {
            $this->line("      → {$risk['ingredient']->name}: {$risk['total_quantity']} unidades");
        }

        // Paso 3: Generar Creaciones del Chef (MÚLTIPLES VARIACIONES)
        $this->line('');
        $this->info('👨‍🍳 Generando recetas especiales...');
        
        $suggestions = [];
        $recipeVariations = [
            ['prefix' => 'Special', 'style' => 'Burger', 'description' => 'Edición limitada con extra'],
            ['prefix' => 'Deluxe', 'style' => 'Wrap', 'description' => 'Versión gourmet en wrap de espinaca'],
            ['prefix' => 'Supreme', 'style' => 'Bowl', 'description' => 'Bowl saludable con vegetales frescos'],
            ['prefix' => 'Premium', 'style' => 'Sandwich', 'description' => 'Sandwich artesanal en pan brioche'],
            ['prefix' => 'Lovers', 'style' => 'Burger XL', 'description' => 'Burger doble con porción extra'],
        ];
        
        foreach ($topRisks as $riskIndex => $risk) {
            $ingredient = $risk['ingredient'];
            $ingredientShort = explode(' ', $ingredient->name)[0];
            
            // Generar 1-2 variaciones por ingrediente (dependiendo de cantidad crítica)
            $variationsToCreate = $risk['total_quantity'] > 1500 ? 2 : 1;
            
            for ($v = 0; $v < $variationsToCreate; $v++) {
                $variation = $recipeVariations[($riskIndex * 2 + $v) % count($recipeVariations)];
                $suggestedName = "{$variation['prefix']} {$ingredientShort} {$variation['style']}";
                
                // Variar costos según el estilo
                $ingredientMultiplier = $variation['style'] === 'Burger XL' ? 4 : 3;
                $baseCost = ($panBase->unit_cost ?? 50) + ($carneBase->unit_cost ?? 200) + (($ingredient->unit_cost ?? 0) * $ingredientMultiplier);
                $suggestedPrice = round($baseCost * 1.30, 2);
                
                $suggestions[] = [
                    'name' => $suggestedName,
                    'ingredient_star' => $ingredient->name,
                    'ingredient_id' => $ingredient->id,
                    'quantity_to_use' => $ingredientMultiplier,
                    'recipe_structure' => [
                        ['ingredient_id' => $panBase->id, 'name' => $panBase->name, 'quantity' => 1],
                        ['ingredient_id' => $carneBase->id, 'name' => $carneBase->name, 'quantity' => 1],
                        ['ingredient_id' => $ingredient->id, 'name' => $ingredient->name, 'quantity' => $ingredientMultiplier],
                    ],
                    'suggested_price' => $suggestedPrice,
                    'description' => "{$variation['description']} {$ingredient->name}",
                    'style' => $variation['style'],
                ];
                
                $this->info("   ✨ Creación: {$suggestedName} (\${$suggestedPrice})");
            }
        }

        // Paso 4: Notificar Administradores
        $this->line('');
        $this->info('📧 Enviando sugerencias...');
        
        $admins = User::whereHas('roles', fn($q) => $q->whereIn('name', ['super_admin', 'administrador']))->get();

        if ($admins->isEmpty()) {
            $this->warn('⚠️ No hay administradores.');
            return Command::SUCCESS;
        }

        foreach ($suggestions as $suggestion) {
            $recipeJson = base64_encode(json_encode($suggestion));
            $campaignUrl = SendCampaign::getUrl(['suggested_recipe' => $recipeJson]);

            Notification::make()
                ->title("💡 Idea de Nuevo Plato: {$suggestion['name']}")
                ->body("Exceso de **{$suggestion['ingredient_star']}**. Sugerencia: **{$suggestion['name']}** (incluye extra {$suggestion['ingredient_star']}).\n\nPrecio: \${$suggestion['suggested_price']}")
                ->icon('heroicon-o-light-bulb')
                ->iconColor('warning')
                ->actions([
                    Action::make('create_campaign')
                        ->label('Crear Campaña')
                        ->icon('heroicon-o-megaphone')
                        ->color('success')
                        ->button()
                        ->url($campaignUrl),
                ])
                ->sendToDatabase($admins);
        }

        $this->info("✅ " . count($suggestions) . " sugerencia(s) enviada(s).");
        $this->info('👨‍🍳 Chef Inteligente completado.');

        return Command::SUCCESS;
    }
}
