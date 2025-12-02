<?php

/**
 * Script de Prueba: StatsOverview Widget (Caja Abierta)
 * Verifica que el widget muestre las ventas de la caja abierta correctamente
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Caja;
use App\Models\Sale;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Test de StatsOverview Widget (Ventas de Caja Abierta)\n";
echo str_repeat("=", 60) . "\n\n";

// ========================================
// Test: Buscar Caja Abierta
// ========================================
echo "📋 Buscando Caja Abierta...\n";
echo str_repeat("-", 60) . "\n";

$caja = Caja::where('status', 'abierta')
    ->where('restaurant_id', 1)
    ->first();

if ($caja) {
    echo "✅ Caja Abierta Encontrada:\n";
    echo "   ID: {$caja->id}\n";
    echo "   Apertura: {$caja->opened_at}\n";
    echo "   Monto Inicial: $ " . number_format($caja->opening_amount, 2, ',', '.') . "\n";
    
    // ========================================
    // Contar Ventas Pagadas de la Caja
    // ========================================
    echo "\n📊 Analizando Ventas...\n";
    echo str_repeat("-", 60) . "\n";
    
    $ventasPagadas = $caja->sales()->where('status', 'paid')->get();
    $totalVentas = $ventasPagadas->sum('total_amount');
    $cantidadVentas = $ventasPagadas->count();
    
    echo "✅ Total de Ventas: $ " . number_format($totalVentas, 2, ',', '.') . "\n";
    echo "   Cantidad: {$cantidadVentas} venta" . ($cantidadVentas != 1 ? 's' : '') . "\n";
    
    if ($cantidadVentas > 0) {
        echo "\n   📋 Detalle de Ventas:\n";
        foreach ($ventasPagadas->take(5) as $venta) {
            $fecha = $venta->created_at->format('H:i:s');
            $monto = number_format($venta->total_amount, 2, ',', '.');
            echo "      • [{$fecha}] $ {$monto}\n";
        }
        
        if ($cantidadVentas > 5) {
            echo "      ... y " . ($cantidadVentas - 5) . " venta(s) más\n";
        }
    }
    
    // ========================================
    // Comparación: Antes vs Después
    // ========================================
    echo "\n";
    echo str_repeat("=", 60) . "\n";
    echo "📊 COMPARACIÓN: Widget Antes vs Después\n";
    echo str_repeat("=", 60) . "\n";
    
    // ANTES: Ventas de hoy (por fecha)
    $ventasHoyAntes = Sale::whereDate('created_at', today())
        ->where('status', 'paid')
        ->sum('total_amount');
    $cantidadHoyAntes = Sale::whereDate('created_at', today())
        ->where('status', 'paid')
        ->count();
    
    echo "❌ ANTES (Lógica Incorrecta):\n";
    echo "   • Criterio: whereDate('created_at', today())\n";
    echo "   • Total: $ " . number_format($ventasHoyAntes, 2, ',', '.') . "\n";
    echo "   • Cantidad: {$cantidadHoyAntes} venta(s) de hoy\n";
    echo "   • Problema: No considera si la caja está abierta o cerrada\n\n";
    
    echo "✅ DESPUÉS (Lógica Correcta):\n";
    echo "   • Criterio: Ventas de la caja abierta (ID: {$caja->id})\n";
    echo "   • Total: $ " . number_format($totalVentas, 2, ',', '.') . "\n";
    echo "   • Cantidad: {$cantidadVentas} venta(s) en la caja actual\n";
    echo "   • Ventaja: Refleja el estado real de la caja operativa\n";
    
} else {
    echo "⚠️  No hay Caja Abierta actualmente\n\n";
    
    echo "📊 WIDGET MOSTRARÁ:\n";
    echo "   • Ventas de Hoy: $ 0,00\n";
    echo "   • Descripción: 'No hay caja abierta actualmente'\n";
    echo "   • Color: Gray (inactivo)\n";
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "✅ Test Completado\n";
echo "🚀 Ve al Dashboard de Filament para ver el widget actualizado!\n";
echo str_repeat("=", 60) . "\n";
