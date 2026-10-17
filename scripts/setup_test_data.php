<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cliente;
use App\Models\IngredientBatch;

// 1. Cumpleaños hoy
Cliente::where('id', 1)->update(['birthday' => now()->format('Y-m-d')]);
$c = Cliente::find(1);
echo "✅ Cumpleaños actualizado: {$c->name} → {$c->birthday}\n";

// 2. Lote de Queso Cheddar venciendo en 3 días
$batch = IngredientBatch::create([
    'ingredient_id'   => 10,
    'quantity'        => 500,
    'expiration_date' => now()->addDays(3)->format('Y-m-d'),
    'purchase_date'   => now()->format('Y-m-d'),
]);
echo "✅ Lote de Queso Cheddar creado (id:{$batch->id}), vence: " . now()->addDays(3)->format('Y-m-d') . "\n";

echo "\nDatos de prueba listos.\n";
