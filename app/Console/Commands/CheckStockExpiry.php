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
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:check-expiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detecta ingredientes próximos a vencer y sugiere productos para evitar desperdicio';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('♻️  Sistema Anti-Desperdicio - Analizando lotes...');

        // ========================================
        // Paso 1: Detectar Lotes en Riesgo
        // ========================================
        $this->line('');
        $this->info('🔍 Buscando lotes próximos a vencer (≤ 3 días)...');

        // Lista de ingredientes a ignorar (insumos base sin potencial de promo directa)
        // LISTA NEGRA AMPLIADA
        $ignoredIngredients = [
            'Harina',
            'Levadura',
            'Sal',
            'Azúcar',
            'Agua',
            'Aceite',
            'Papas Congeladas',
            'Aceite de Oliva',
            'Vinagre',
            'Pimienta',
            'Huevo',        // 🚫 Huevos crudos no se promocionan solos
            'Huevos',
        ];

        // Buscar lotes que vencen en 3 días o menos y tienen stock
        // Excluir ingredientes base que no generan promos directas
        $expiringBatches = IngredientBatch::where('quantity', '>', 0)
            ->where('expiration_date', '<=', now()->addDays(3))
            ->where('expiration_date', '>=', now()) // Solo futuros, no vencidos
            ->whereHas('ingredient', fn($q) => $q->whereNotIn('name', $ignoredIngredients))
            ->with('ingredient')
            ->get();

        if ($expiringBatches->isEmpty()) {
            $this->info('✅ No hay lotes en riesgo de vencimiento.');
            $this->comment('💡 Todos los ingredientes están bajo control.');
            return Command::SUCCESS;
        }

        $this->warn("⚠️  Se encontraron {$expiringBatches->count()} lote(s) en riesgo.");

        // ========================================
        // Paso 2: Agrupar y Analizar por Ingrediente
        // ========================================
        $this->line('');
        $this->info('📊 Agrupando por ingrediente...');

        // Agrupar lotes por ingrediente y sumar cantidades
        $ingredientRisks = $expiringBatches->groupBy('ingredient_id')->map(function ($batches) {
            $ingredient = $batches->first()->ingredient;
            $totalQuantity = $batches->sum('quantity');
            $nearestExpiry = $batches->min('expiration_date');

            return [
                'ingredient' => $ingredient,
                'total_quantity' => $totalQuantity,
                'nearest_expiry' => $nearestExpiry,
                'batch_count' => $batches->count(),
            ];
        });

        // Ordenar por cantidad total (mayor primero)
        $ingredientRisks = $ingredientRisks->sortByDesc('total_quantity');

        // Mostrar resumen
        foreach ($ingredientRisks as $risk) {
            $daysUntilExpiry = now()->diffInDays($risk['nearest_expiry']);
            $this->line("   • {$risk['ingredient']->name}: {$risk['total_quantity']} unidades (vence en {$daysUntilExpiry} día(s))");
        }

        // Seleccionar el ingrediente más crítico (mayor cantidad)
        $criticalRisk = $ingredientRisks->first();
        $criticalIngredient = $criticalRisk['ingredient'];

        $this->line('');
        $this->warn("🎯 Ingrediente Crítico: {$criticalIngredient->name} ({$criticalRisk['total_quantity']} unidades)");

        // ========================================
        // Paso 3: Estrategia - Buscar Productos que Usen el Ingrediente
        // ========================================
        $this->line('');
        $this->info('👨‍🍳 Chef Digital: Buscando productos que usen este ingrediente...');

        // Buscar productos que tengan este ingrediente en su receta
        $recommendedProducts = Product::whereHas('recipe.ingredients', function ($query) use ($criticalIngredient) {
            $query->where('ingredients.id', $criticalIngredient->id);
        })
        ->with(['recipe.ingredients' => function ($query) use ($criticalIngredient) {
            $query->where('ingredients.id', $criticalIngredient->id);
        }])
        ->with('category') // Cargar categoría para estrategia
        ->get();

        if ($recommendedProducts->isEmpty()) {
            $this->warn('⚠️  No se encontraron productos que usen este ingrediente.');
            $this->comment('💡 Considera crear una nueva receta o ajustar las existentes.');
            return Command::SUCCESS;
        }

        $this->info("   ✅ Se encontraron {$recommendedProducts->count()} producto(s) que usan {$criticalIngredient->name}:");
        foreach ($recommendedProducts as $product) {
            $ingredientInRecipe = $product->recipe->ingredients->first();
            $quantity = $ingredientInRecipe->pivot->required_amount ?? 0;
            $categoryName = $product->category ? $product->category->name : 'Sin categoría';
            $this->line("      → {$product->name} [{$categoryName}] (usa {$quantity} unidades por producto)");
        }

        // ========================================
        // Estrategia de Selección Inteligente
        // ========================================
        $this->line('');
        $this->info('🧠 Analizando estrategia óptima...');
        
        // Caso especial: Si es QUESO CHEDDAR, buscar productos con MUCHO queso
        if (stripos($criticalIngredient->name, 'Queso') !== false || 
            stripos($criticalIngredient->name, 'Cheddar') !== false) {
            
            $this->info('   🧀 Detectado QUESO → Buscando productos con alto uso de queso...');
            
            // Buscar productos que usen MUCHO queso (cantidad alta en receta)
            $cheeseProducts = $recommendedProducts->map(function ($product) use ($criticalIngredient) {
                $ingredientInRecipe = $product->recipe->ingredients
                    ->where('id', $criticalIngredient->id)
                    ->first();
                
                $quantity = $ingredientInRecipe ? ($ingredientInRecipe->pivot->required_amount ?? 0) : 0;
                
                return [
                    'product' => $product,
                    'cheese_amount' => $quantity,
                ];
            })
            ->filter(fn($item) => $item['cheese_amount'] > 0)
            ->sortByDesc('cheese_amount'); // Ordenar por cantidad de queso (mayor primero)
            
            if ($cheeseProducts->isNotEmpty()) {
                $topCheeseProduct = $cheeseProducts->first();
                $recommendedProduct = $topCheeseProduct['product'];
                $cheeseAmount = $topCheeseProduct['cheese_amount'];
                
                $this->info("   ✅ Producto con MÁS queso: {$recommendedProduct->name} (usa {$cheeseAmount} unidades)");
            }
        }
        
        // Estrategia general: Priorizar hamburguesas sobre otros productos
        if (!isset($recommendedProduct)) {
            $burgers = $recommendedProducts->filter(function ($product) {
                return $product->category && $product->category->name === 'Hamburguesas';
            });

            // Si hay hamburguesas disponibles, elegir la primera; si no, usar cualquier producto
            $recommendedProduct = $burgers->isNotEmpty() ? $burgers->first() : $recommendedProducts->first();
        }

        $this->line('');
        $this->info("💡 Producto Recomendado: {$recommendedProduct->name}");

        // ========================================
        // Paso 4: Notificar a Administradores
        // ========================================
        $this->line('');
        $this->info('📧 Preparando alerta para administradores...');

        $admins = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['super_admin', 'administrador']);
        })->get();

        if ($admins->isEmpty()) {
            $this->warn('⚠️  No se encontraron administradores para notificar.');
            return Command::SUCCESS;
        }

        // Calcular días hasta el vencimiento
        $daysUntilExpiry = now()->diffInDays($criticalRisk['nearest_expiry']);
        
        // Preparar contenido de la notificación
        $title = "♻️ Alerta Anti-Desperdicio: {$criticalIngredient->name}";
        $body = "Tienes **{$criticalRisk['total_quantity']} unidades** de {$criticalIngredient->name} que vencen en **{$daysUntilExpiry} día(s)**. "
            . "El sistema sugiere lanzar una promo de **{$recommendedProduct->name}** para consumirlo rápidamente y evitar pérdidas.";

        // URL de campaña con datos pre-llenados + DESCUENTO FIJO
        $campaignUrl = SendCampaign::getUrl([
            'product_id' => $recommendedProduct->id,
            'subject' => $title,
            'body' => $body . "\n\n🎁 **Oferta Especial**: {$recommendedProduct->name} - ¡Aprovecha antes que se acabe!\n\nEsta promo ayuda a reducir desperdicio y maximizar ganancias.",
            'discount_type' => 'fixed',
            'discount_value' => 500,
            'coupon_code' => 'NOPIERDO',
        ]);

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('warning')
            ->actions([
                Action::make('create_campaign')
                    ->label('Crear Campaña')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->button()
                    ->url($campaignUrl),
                
                Action::make('view_ingredient')
                    ->label('Ver Ingrediente')
                    ->icon('heroicon-o-cube')
                    ->color('gray')
                    ->url("/admin/ingredients/{$criticalIngredient->id}/edit")
                    ->openUrlInNewTab(true),
            ])
            ->sendToDatabase($admins);

        $this->info("📧 Notificación enviada a {$admins->count()} administrador(es).");
        $this->line('');
        $this->info('✅ Sistema Anti-Desperdicio completado.');
        $this->comment("♻️  Ingrediente: {$criticalIngredient->name} | Cantidad: {$criticalRisk['total_quantity']} | Vence en: {$daysUntilExpiry} día(s)");
        $this->comment("💡 Producto sugerido: {$recommendedProduct->name}");

        return Command::SUCCESS;
    }
}
