<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Crea usuarios de prueba para los roles de staff:
     * - Mozo
     * - Admin de Cocina
     */
    public function run(): void
    {
        // Usuario 1: Mozo
        $mozo = User::firstOrCreate(
            ['email' => 'mozo@moodi.com'],
            [
                'name' => 'Mozo Prueba',
                'password' => Hash::make('password'),
                'restaurant_id' => 1,
            ]
        );
        $mozo->assignRole('Mozo');
        $this->command->info('✅ Usuario Mozo creado: mozo@moodi.com (password: password)');

        // Usuario 2: Admin de Cocina
        $cocina = User::firstOrCreate(
            ['email' => 'cocina@moodi.com'],
            [
                'name' => 'Cocinero Prueba',
                'password' => Hash::make('password'),
                'restaurant_id' => 1,
            ]
        );
        $cocina->assignRole('Admin Cocina');
        $this->command->info('✅ Usuario Admin de Cocina creado: cocina@moodi.com (password: password)');

        $this->command->info('');
        $this->command->comment('🎭 Usuarios de staff creados exitosamente');
        $this->command->comment('   Mozo: mozo@moodi.com');
        $this->command->comment('   Cocina: cocina@moodi.com');
        $this->command->comment('   Password: password (para ambos)');
    }
}

