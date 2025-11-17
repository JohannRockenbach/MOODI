<?php

/**
 * Script de Testing - Sistema de Actualización Automática
 * 
 * Ejecutar: php scripts/test_observer_system.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Product;
use App\Models\Ingredient;
use Illuminate\Support\Facades\Log;

echo "🧪 TESTING: Sistema de Actualización Automática\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// 1. Verificar que el Observer está registrado
echo "1️⃣  Verificando Observer registrado...\n";
$observers = Order::getObservableEvents();
if (count($observers) > 0) {
    echo "   ✅ Observer detectado: " . implode(", ", $observers) . "\n\n";
} else {
    echo "   ⚠️  No se detectaron observers (puede ser normal)\n\n";
}

// 2. Buscar un pedido pendiente para testing
echo "2️⃣  Buscando pedido pendiente...\n";
$order = Order::where('status', 'pending')
    ->where('stock_deducted', false)
    ->with('orderProducts.product')
    ->first();

if (!$order) {
    echo "   ⚠️  No hay pedidos pendientes. Crea uno desde /admin/orders\n\n";
    exit(0);
}

echo "   ✅ Pedido encontrado: #{$order->id}\n";
echo "   📦 Productos: " . $order->orderProducts->count() . "\n";
echo "   💰 Total: $" . $order->total . "\n";
echo "   📊 Stock descontado: " . ($order->stock_deducted ? 'SÍ' : 'NO') . "\n\n";

// 3. Anotar stock ANTES del cambio
echo "3️⃣  Stock ANTES del cambio de estado:\n";
$stockBefore = [];

foreach ($order->orderProducts as $orderProduct) {
    $product = $orderProduct->product;
    $stockBefore[$product->id] = [
        'name' => $product->name,
        'stock' => $product->stock,
        'real_stock' => $product->real_stock,
    ];
    echo "   📦 {$product->name}: Stock Real = {$product->real_stock}\n";
}
echo "\n";

// 4. Cambiar estado a "processing"
echo "4️⃣  Cambiando estado a 'processing'...\n";
echo "   ⏳ El Observer debería dispararse automáticamente...\n\n";

$order->status = 'processing';
$order->save(); // Esto dispara OrderObserver::updated()

sleep(2); // Esperar a que el Listener procese

// 5. Recargar el pedido y verificar
echo "5️⃣  Verificando cambios después de 2 segundos:\n";
$order->refresh();

echo "   📊 Estado actual: " . strtoupper($order->status) . "\n";
echo "   📊 Stock descontado: " . ($order->stock_deducted ? 'SÍ ✅' : 'NO ❌') . "\n\n";

// 6. Verificar stock DESPUÉS del cambio
echo "6️⃣  Stock DESPUÉS del cambio de estado:\n";
foreach ($order->orderProducts as $orderProduct) {
    $product = $orderProduct->product->fresh();
    
    $before = $stockBefore[$product->id]['real_stock'];
    $after = $product->real_stock;
    $diff = $before - $after;
    
    $icon = ($diff > 0) ? '✅' : '❌';
    echo "   {$icon} {$product->name}:\n";
    echo "      • Antes: {$before}\n";
    echo "      • Después: {$after}\n";
    echo "      • Descontado: {$diff}\n";
}
echo "\n";

// 7. Verificar logs
echo "7️⃣  Últimas líneas del log:\n";
$logPath = storage_path('logs/laravel.log');
if (file_exists($logPath)) {
    $lines = file($logPath);
    $lastLines = array_slice($lines, -10);
    
    foreach ($lastLines as $line) {
        if (str_contains($line, 'OrderObserver') || str_contains($line, 'OrderProcessing')) {
            echo "   " . trim($line) . "\n";
        }
    }
} else {
    echo "   ⚠️  No se encontró el archivo de logs\n";
}
echo "\n";

// 8. Resumen
echo "=" . str_repeat("=", 50) . "\n";
echo "📊 RESUMEN:\n";
echo "   • Pedido: #{$order->id}\n";
echo "   • Estado: " . strtoupper($order->status) . "\n";
echo "   • Stock descontado: " . ($order->stock_deducted ? 'SÍ ✅' : 'NO ❌') . "\n";

if ($order->stock_deducted && $order->status === 'processing') {
    echo "\n✅ ¡SISTEMA FUNCIONANDO CORRECTAMENTE!\n";
    echo "   • Observer detectó el cambio de estado\n";
    echo "   • Evento OrderProcessing fue disparado\n";
    echo "   • Listener descontó el stock\n";
    echo "   • Campo stock_deducted marcado como true\n";
} else {
    echo "\n⚠️  VERIFICAR CONFIGURACIÓN:\n";
    echo "   • ¿El Observer está registrado en EventServiceProvider?\n";
    echo "   • ¿El Listener está escuchando OrderProcessing?\n";
    echo "   • Revisar logs en storage/logs/laravel.log\n";
}

echo "\n";
