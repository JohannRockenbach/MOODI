<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Comentamos seeders antiguos
        // User::factory(10)->create();
        
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        
        // $this->call(\Database\Seeders\CategorySeeder::class);
        // $this->call(\Database\Seeders\OrderReservationSaleSeeder::class);

        // Fase 0 (obligatoria): roles y permisos base ANTES que cualquier seeder
        // dependiente de usuarios/roles/permisos. Ambos son idempotentes:
        // SuperAdminSeeder usa firstOrCreate y ShieldSeeder regenera permisos sin duplicar.
        try {
            $this->call(SuperAdminSeeder::class);
            $this->call(ShieldSeeder::class);
        } catch (\Throwable $e) {
            $this->command->warn('Seeders base (SuperAdmin/Shield) no ejecutados: ' . $e->getMessage());
        }

        // Ejecutamos solo el nuevo seeder de hamburguesería
        $this->call([
            BurgerMenuSeeder::class,
            StaffSeeder::class, // Usuarios de prueba para Mozo y Cocina
            TablePositionSeeder::class, // Mesas con posiciones para el floor plan
        ]);
    }
}
