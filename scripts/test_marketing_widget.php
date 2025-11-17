<?php

/**
 * Script de Prueba: MarketingOverview Widget
 * Verifica que el widget muestre datos correctos de las 3 automatizaciones
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Cliente;
use App\Models\IngredientBatch;
use App\Services\WeatherService;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Test de MarketingOverview Widget\n";
echo str_repeat("=", 60) . "\n\n";

// ========================================
// Test 1: Clima
// ========================================
echo "📋 Test 1: Datos de Clima\n";
echo str_repeat("-", 60) . "\n";

$weatherService = app(WeatherService::class);
$weather = $weatherService->getCurrentWeather();

if ($weather) {
    $temp = $weather['temperature'] ?? 0;
    $isRaining = $weatherService->isRaining($weather);
    
    echo "✅ Temperatura: {$temp}°C\n";
    echo "   Lluvia: " . ($isRaining ? '🌧️  SÍ' : '☀️  NO') . "\n";
    
    if ($isRaining) {
        echo "   🎯 Oportunidad: Combo Netflix (Lluvia)\n";
    } elseif ($temp > 28) {
        echo "   🎯 Oportunidad: After Office (Calor)\n";
    } else {
        echo "   🎯 Oportunidad: Menú Ejecutivo (Estándar)\n";
    }
} else {
    echo "⚠️  API de clima no disponible\n";
}

echo "\n";

// ========================================
// Test 2: Anti-Desperdicio
// ========================================
echo "📋 Test 2: Ingredientes en Riesgo\n";
echo str_repeat("-", 60) . "\n";

$ignoredIngredients = [
    'Harina', 'Levadura', 'Sal', 'Azúcar', 'Agua', 
    'Aceite', 'Papas Congeladas', 'Aceite de Oliva', 
    'Vinagre', 'Pimienta',
];

$expiringBatches = IngredientBatch::where('quantity', '>', 0)
    ->where('expiration_date', '<=', now()->addDays(3))
    ->where('expiration_date', '>=', now())
    ->whereHas('ingredient', fn($q) => $q->whereNotIn('name', $ignoredIngredients))
    ->with('ingredient')
    ->get();

$uniqueIngredients = $expiringBatches->unique('ingredient_id');

echo "✅ Total lotes en riesgo: " . $expiringBatches->count() . "\n";
echo "   Ingredientes únicos: " . $uniqueIngredients->count() . "\n";

if ($uniqueIngredients->count() > 0) {
    echo "   ⚠️  Ingredientes críticos:\n";
    foreach ($uniqueIngredients as $batch) {
        $days = now()->diffInDays($batch->expiration_date);
        echo "      → {$batch->ingredient->name} ({$batch->quantity} unidades, vence en {$days} día" . ($days > 1 ? 's' : '') . ")\n";
    }
} else {
    echo "   ✅ Sin ingredientes en riesgo\n";
}

echo "\n";

// ========================================
// Test 3: Fidelización
// ========================================
echo "📋 Test 3: Oportunidades de Fidelización\n";
echo str_repeat("-", 60) . "\n";

$birthdaysToday = Cliente::whereMonth('birthday', now()->month)
    ->whereDay('birthday', now()->day)
    ->get();

$vipClients = Cliente::whereHas('orders', function ($query) {
    $query->where('created_at', '>=', now()->subDays(30));
}, '>=', 5)
->withCount([
    'orders' => fn($q) => $q->where('created_at', '>=', now()->subDays(30))
])
->get();

echo "✅ Cumpleaños hoy: " . $birthdaysToday->count() . "\n";
if ($birthdaysToday->count() > 0) {
    foreach ($birthdaysToday as $client) {
        echo "      🎂 {$client->name} ({$client->email})\n";
    }
}

echo "\n";

echo "✅ Clientes VIP (5+ pedidos en 30 días): " . $vipClients->count() . "\n";
if ($vipClients->count() > 0) {
    foreach ($vipClients as $client) {
        echo "      👑 {$client->name} ({$client->orders_count} pedidos)\n";
    }
}

$totalOpportunities = $birthdaysToday->count() + $vipClients->count();

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "📊 RESUMEN DEL WIDGET:\n";
echo str_repeat("=", 60) . "\n";
echo "1. Clima: " . ($weather ? "{$temp}°C - " . ($isRaining ? 'Lluvia' : ($temp > 28 ? 'Calor' : 'Estándar')) : 'No disponible') . "\n";
echo "2. Anti-Desperdicio: " . ($uniqueIngredients->count() > 0 ? "{$uniqueIngredients->count()} ingrediente(s) en riesgo" : "Sin riesgos") . "\n";
echo "3. Fidelización: {$totalOpportunities} oportunidad" . ($totalOpportunities != 1 ? 'es' : '') . " ({$birthdaysToday->count()} cumpleaños + {$vipClients->count()} VIP)\n";
echo str_repeat("=", 60) . "\n";
echo "\n🚀 Ahora ve al Dashboard de Filament para ver el widget en acción!\n";
