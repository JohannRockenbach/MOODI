<?php

/**
 * Script de prueba para el sistema de recetas sugeridas
 * 
 * Uso: php scripts/test_suggested_recipes.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Recipe;
use App\Models\IngredientBatch;
use Illuminate\Support\Facades\DB;

echo "\n=== Test de Sistema de Recetas Sugeridas ===\n\n";

// 1. Buscar ingredientes con fecha de expiración próxima (< 3 días)
echo "1️⃣ Buscando ingredientes próximos a vencer...\n";

$criticalIngredients = IngredientBatch::where('expiration_date', '<=', now()->addDays(3))
    ->where('expiration_date', '>=', now())
    ->where('quantity', '>', 0)
    ->with('ingredient')
    ->get();

if ($criticalIngredients->isEmpty()) {
    echo "   ❌ No hay ingredientes próximos a vencer\n\n";
    
    // Crear uno de prueba
    echo "2️⃣ Creando ingrediente con vencimiento cercano de prueba...\n";
    $testIngredient = IngredientBatch::first();
    if ($testIngredient) {
        $testIngredient->update([
            'expiration_date' => now()->addDays(2),
        ]);
        echo "   ✅ Ingrediente ID {$testIngredient->id} ahora vence en 2 días\n";
        $criticalIngredients = collect([$testIngredient->load('ingredient')]);
    }
} else {
    echo "   ✅ Encontrados " . $criticalIngredients->count() . " ingredientes próximos a vencer:\n";
    foreach ($criticalIngredients as $ingredient) {
        $daysUntilExpiry = now()->diffInDays($ingredient->expiration_date);
        echo "      • {$ingredient->ingredient->name}: vence en {$daysUntilExpiry} días ({$ingredient->expiration_date->format('d/m/Y')})\n";
    }
}

echo "\n3️⃣ Buscando recetas que usan estos ingredientes...\n";

$suggestedRecipes = [];

foreach ($criticalIngredients as $criticalBatch) {
    // Buscar recetas que usan este ingrediente
    $recipes = Recipe::whereHas('ingredients', function ($query) use ($criticalBatch) {
        $query->where('ingredients.id', $criticalBatch->ingredient_id);
    })
    ->with('ingredients')
    ->get();
    
    if ($recipes->isNotEmpty()) {
        echo "   📋 Ingrediente: {$criticalBatch->ingredient->name}\n";
        foreach ($recipes as $recipe) {
            echo "      ✅ Receta: {$recipe->name}\n";
            $suggestedRecipes[] = [
                'recipe_id' => $recipe->id,
                'recipe_name' => $recipe->name,
                'ingredient_id' => $criticalBatch->ingredient_id,
                'ingredient_name' => $criticalBatch->ingredient->name,
                'stock' => $criticalBatch->quantity,
                'expiration_date' => $criticalBatch->expiration_date->format('d/m/Y'),
            ];
        }
    }
}

if (empty($suggestedRecipes)) {
    echo "   ❌ No se encontraron recetas para los ingredientes críticos\n";
} else {
    echo "\n4️⃣ Resumen de recetas sugeridas:\n";
    echo "   Total: " . count($suggestedRecipes) . " recetas\n\n";
    
    foreach ($suggestedRecipes as $index => $recipe) {
        echo "   " . ($index + 1) . ". {$recipe['recipe_name']}\n";
        echo "      Ingrediente crítico: {$recipe['ingredient_name']}\n";
        echo "      Stock restante: {$recipe['stock']}\n";
        echo "      Fecha de vencimiento: {$recipe['expiration_date']}\n\n";
    }
}

echo "=== Test Completado ===\n\n";
