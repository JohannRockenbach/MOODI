<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Crea usuarios de prueba y PERMISOS de tareas para los roles de staff
     * (single-restaurant MOODI):
     *
     *  - Mozo     → operación de salón: mesas, pedidos (crear/ver), catálogo (ver).
     *  - Cajero   → caja abierta/cierre + ventas (ver/registrar).
     *  - Cocinero → pantalla de cocina + catálogo (ver productos/recetas/ingredientes).
     *
     * Roles reales del sistema: super_admin, Mozo, Cajero, Cocinero (guard 'web').
     * Los roles se garantizan con firstOrCreate para que el seeder sea
     * re-ejecutable e independiente del orden de otros seeders.
     */
    public function run(): void
    {
        // ←── ROLES ──→
        $mozoRole = Role::firstOrCreate(['name' => 'Mozo', 'guard_name' => 'web']);
        $cajeroRole = Role::firstOrCreate(['name' => 'Cajero', 'guard_name' => 'web']);
        $cocineroRole = Role::firstOrCreate(['name' => 'Cocinero', 'guard_name' => 'web']);

        // ── MOZO: operación de salón ──
        $mozoPermissions = [
            'view_any_product', 'view_product', 'view_any_recipe', 'view_recipe', 'view_any_ingredient', 'view_ingredient',
            'view_any_table', 'view_table', 'create_table', 'update_table', 'assign_table_table', 'select_table_table',
            'view_any_reservation', 'view_reservation', 'create_reservation',
            'view_any_order', 'view_order', 'create_order',
        ];
        $this->ensurePermissions($mozoPermissions);
        $mozoRole->syncPermissions($mozoPermissions);

        // ── CAJERO: caja + ventas ──
        $cajeroPermissions = [
            'view_any_caja', 'view_caja', 'open_caja', 'close_caja', 'update_caja', 'create_caja',
            'view_any_sale', 'view_sale', 'create_sale',
        ];
        $this->ensurePermissions($cajeroPermissions);
        $cajeroRole->syncPermissions($cajeroPermissions);

        // ── COCINERO: cocina + catálogo (lectura) ──
        $cocineroPermissions = [
            'view_any_product', 'view_product',
            'view_any_recipe', 'view_recipe',
            'view_any_ingredient', 'view_ingredient',
            'view_any_order', 'view_order',
        ];
        $this->ensurePermissions($cocineroPermissions);
        $cocineroRole->syncPermissions($cocineroPermissions);

        // ←── USUARIOS DE PRUEBA ──→
        // Reutilizar filas soft-deleted por email; restaurarlas o crearlas.
        $mozo = $this->findOrCreateStaff('mozo@moodi.com', 'Mozo Prueba');
        $mozo->syncRoles([$mozoRole]);
        $this->command->info('✅ Usuario Mozo creado: mozo@moodi.com (password: password)');

        $cocina = $this->findOrCreateStaff('cocina@moodi.com', 'Cocinero Prueba');
        $cocina->syncRoles([$cocineroRole]);
        $this->command->info('✅ Usuario Cocinero creado: cocina@moodi.com (password: password)');

        $cajero = $this->findOrCreateStaff('cajero@moodi.com', 'Cajero Prueba');
        $cajero->syncRoles([$cajeroRole]);
        $this->command->info('✅ Usuario Cajero creado: cajero@moodi.com (password: password)');

        $this->command->info('');
        $this->command->comment('🎭 Usuarios de staff creados exitosamente');
        $this->command->comment('   Mozo: mozo@moodi.com');
        $this->command->comment('   Cocinero: cocina@moodi.com');
        $this->command->comment('   Cajero: cajero@moodi.com');
        $this->command->comment('   Password: password (para todos)');
    }

    /**
     * Garantizar que existan los permisos dados para el guard 'web'.
     *
     * En entornos donde FilamentShield aún no generó la tabla de permisos
     * (p. ej. tests con RefreshDatabase), hay que crearlos antes de asignarlos.
     *
     * @param string[] $permissionNames
     */
    private function ensurePermissions(array $permissionNames): void
    {
        foreach ($permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }
    }

    /**
     * Buscar un usuario de staff por email (INCLUYENDO soft-deleted) y
     * restaurarlo/actualizarlo, o crearlo si no existe.
     *
     * Evita el choque de UNIQUE(email)/PK cuando una fila borrada reutiliza
     * un email (p. ej. 'cocina@moodi.com' fue borrado en versiones previas).
     */
    private function findOrCreateStaff(string $email, string $name): User
    {
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user) {
            $user->restore();
            $user->forceFill([
                'name' => $name,
                'password' => Hash::make('password'),
                'restaurant_id' => 1,
            ])->save();

            return $user;
        }

        return User::create([
            'email' => $email,
            'name' => $name,
            'password' => Hash::make('password'),
            'restaurant_id' => 1,
        ]);
    }
}