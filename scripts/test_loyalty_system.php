<?php

/**
 * Script de Prueba: Sistema de Fidelización
 * Simula cumpleaños y clientes VIP para probar CheckLoyaltyPromo
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Cliente;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Sistema de Prueba: Fidelización\n";
echo str_repeat("=", 50) . "\n\n";

// ========================================
// Test 1: Crear Cliente con Cumpleaños HOY
// ========================================
echo "📋 Test 1: Cliente con cumpleaños hoy\n";
echo str_repeat("-", 50) . "\n";

$birthdayClient = Cliente::firstOrCreate(
    ['email' => 'cumpleanero@test.com'],
    [
        'name' => 'Juan Cumpleañero',
        'phone' => '0351-1234567',
        'birthday' => now()->format('Y-m-d'), // HOY pero año pasado
    ]
);

// Ajustar para que sea "hoy" pero hace años
$birthdayClient->update([
    'birthday' => now()->subYears(25)->format('Y-m-d')
]);

echo "✅ Cliente creado: {$birthdayClient->name}\n";
echo "   📅 Cumpleaños: {$birthdayClient->birthday->format('d/m/Y')}\n";
echo "   🎂 Edad: " . now()->diffInYears($birthdayClient->birthday) . " años\n";
echo "   📧 Email: {$birthdayClient->email}\n\n";

// ========================================
// Test 2: Crear Cliente VIP (5+ pedidos)
// ========================================
echo "📋 Test 2: Cliente VIP (5 pedidos en 30 días)\n";
echo str_repeat("-", 50) . "\n";

$vipClient = Cliente::firstOrCreate(
    ['email' => 'clientevip@test.com'],
    [
        'name' => 'María VIP',
        'phone' => '0351-7654321',
        'birthday' => now()->subYears(30)->format('Y-m-d'),
    ]
);

echo "✅ Cliente creado: {$vipClient->name}\n";

// Crear 6 pedidos en los últimos 30 días
$existingOrders = Order::where('customer_id', $vipClient->id)
    ->where('created_at', '>=', now()->subDays(30))
    ->count();

$ordersToCreate = max(0, 6 - $existingOrders);

if ($ordersToCreate > 0) {
    echo "   📦 Creando {$ordersToCreate} pedidos...\n";
    
    // Buscar un usuario válido (admin o mozo)
    $waiter = \App\Models\User::first();
    
    if (!$waiter) {
        echo "   ⚠️  No hay usuarios en el sistema. No se pueden crear pedidos.\n";
    } else {
        for ($i = 0; $i < $ordersToCreate; $i++) {
            Order::create([
                'customer_id' => $vipClient->id,
                'restaurant_id' => 1,
                'waiter_id' => $waiter->id,
                'status' => 'completed',
                'type' => 'delivery',
                'delivery_address' => 'Calle Test 123',
                'delivery_phone' => '0351-7654321',
                'customer_name' => $vipClient->name,
                'stock_deducted' => true,
                'created_at' => now()->subDays(rand(1, 29)),
            ]);
        }
    }
}

$totalOrders = Order::where('customer_id', $vipClient->id)
    ->where('created_at', '>=', now()->subDays(30))
    ->count();

echo "   ✅ Total de pedidos en 30 días: {$totalOrders}\n";
echo "   👑 Estado: " . ($totalOrders >= 5 ? 'VIP' : 'Normal') . "\n";
echo "   📧 Email: {$vipClient->email}\n\n";

// ========================================
// Resumen
// ========================================
echo str_repeat("=", 50) . "\n";
echo "✅ Datos de prueba creados exitosamente\n\n";
echo "🚀 Ahora ejecuta: php artisan loyalty:check-promo\n";
echo "   Deberías ver:\n";
echo "   • 1 cumpleaños detectado (Juan Cumpleañero)\n";
echo "   • 1 cliente VIP detectado (María VIP)\n";
echo str_repeat("=", 50) . "\n";
