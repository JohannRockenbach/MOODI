<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Order;
use Illuminate\Database\Seeder;

class BirthdayUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Crea datos de prueba para verificar automatización de fidelización:
     * - Cliente con cumpleaños HOY
     * - Cliente VIP con 6+ pedidos
     */
    public function run(): void
    {
        $this->command->info('🎂 Creando clientes de prueba para Fidelización...');
        
        // Limpiar tabla para evitar duplicados en pruebas
        Cliente::whereIn('email', ['rockenbachjohann@gmail.com', 'vip@test.com'])->delete();
        
        // 1. Cliente Cumpleañero (HOY)
        $cumpleanero = Cliente::create([
            'name' => 'Juan Cumpleañero',
            'email' => 'rockenbachjohann@gmail.com',
            'phone' => '+54 9 11 1234-5678',
            'birthday' => now(), // ¡Cumpleaños hoy!
        ]);
        
        $this->command->info("   ✅ Cliente cumpleañero: {$cumpleanero->name} ({$cumpleanero->email})");
        $this->command->info("      📅 Cumpleaños: " . $cumpleanero->birthday->format('d/m/Y'));
        
        // 2. Cliente VIP (6+ pedidos históricos)
        $vip = Cliente::create([
            'name' => 'Maria VIP',
            'email' => 'vip@test.com',
            'phone' => '+54 9 11 9876-5432',
            'birthday' => now()->subMonths(4), // Cumpleaños en otro mes
        ]);
        
        // Crear 6 pedidos antiguos para este cliente (simular historial)
        $orderDates = [
            now()->subMonths(6),
            now()->subMonths(5),
            now()->subMonths(4),
            now()->subMonths(3),
            now()->subMonths(2),
            now()->subMonth(),
        ];
        
        // Obtener un usuario válido (primer usuario disponible)
        $defaultUser = \App\Models\User::first();
        
        if (!$defaultUser) {
            $this->command->warn('⚠️ No hay usuarios en el sistema. No se pueden crear pedidos.');
            return;
        }
        
        $ordersCreated = 0;
        foreach ($orderDates as $index => $date) {
            Order::create([
                'customer_id' => $vip->id,
                'status' => 'completed',
                'type' => 'delivery',
                'restaurant_id' => $defaultUser->restaurant_id ?? 1,
                'waiter_id' => $defaultUser->id,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
            $ordersCreated++;
        }
        
        $this->command->info("   ✅ Cliente VIP: {$vip->name} ({$vip->email})");
        $this->command->info("      🏆 {$ordersCreated} pedidos históricos creados");
        
        $this->command->info('');
        $this->command->info('🎉 Seeder completado. Ejecuta el comando para probar:');
        $this->command->warn('   php artisan loyalty:check-birthdays');
    }
}

