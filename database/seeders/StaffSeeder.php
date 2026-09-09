<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Crea usuarios de prueba para los roles de staff (single-restaurant MOODI):
     * - Mozo
     * - Cajero
     *
     * Roles reales del sistema: super_admin, Mozo, Cajero (guard 'web').
     * Los roles se garantizan con firstOrCreate para que el seeder sea
     * re-ejecutable e independiente del orden de otros seeders.
     */
    public function run(): void
    {
        // Garantizar que los roles existan antes de asignarlos
        $mozoRole = Role::firstOrCreate(['name' => 'Mozo', 'guard_name' => 'web']);
        $cajeroRole = Role::firstOrCreate(['name' => 'Cajero', 'guard_name' => 'web']);

        // Usuario 1: Mozo
        $mozo = User::firstOrCreate(
            ['email' => 'mozo@moodi.com'],
            [
                'name' => 'Mozo Prueba',
                'password' => Hash::make('password'),
                'restaurant_id' => 1,
            ]
        );
        $mozo->syncRoles([$mozoRole]);
        $this->command->info('✅ Usuario Mozo creado: mozo@moodi.com (password: password)');

        // Usuario 2: demo de cocina. 'Admin Cocina' no existe como rol real,
        // por lo que se mapea al rol operativo 'Cajero'.
        $cocina = User::firstOrCreate(
            ['email' => 'cocina@moodi.com'],
            [
                'name' => 'Cocinero Prueba',
                'password' => Hash::make('password'),
                'restaurant_id' => 1,
            ]
        );
        $cocina->syncRoles([$cajeroRole]);
        $this->command->info('✅ Usuario Cajero (demo cocina) creado: cocina@moodi.com (password: password)');

        $this->command->info('');
        $this->command->comment('🎭 Usuarios de staff creados exitosamente');
        $this->command->comment('   Mozo: mozo@moodi.com');
        $this->command->comment('   Cajero (demo cocina): cocina@moodi.com');
        $this->command->comment('   Password: password (para ambos)');
    }
}
