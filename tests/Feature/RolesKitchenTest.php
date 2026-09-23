<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\KitchenDashboard;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesKitchenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Restaurant::factory()->create(['id' => 1]);

        foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero', 'cliente'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeUser(string $role, string $email): User
    {
        $user = User::factory()->create([
            'name' => $role.' Test',
            'email' => $email,
            'restaurant_id' => 1,
        ]);
        $user->assignRole($role);

        return $user;
    }

    // ─────────────────────────────────────────────────────────────
    // ACCESO AL PANEL (canAccessPanel)
    // ─────────────────────────────────────────────────────────────

    public function test_cocinero_puede_acceder_al_panel(): void
    {
        $cocinero = $this->makeUser('Cocinero', 'cocina@test.com');

        $this->assertTrue($cocinero->canAccessPanel(app(\Filament\Panel::class)));
    }

    public function test_cliente_web_no_puede_acceder_al_panel(): void
    {
        $cliente = $this->makeUser('cliente', 'cliente@test.com');

        $this->assertFalse($cliente->canAccessPanel(app(\Filament\Panel::class)));
    }

    public function test_mozo_y_cajero_pueden_acceder_al_panel(): void
    {
        $mozo = $this->makeUser('Mozo', 'mozo@test.com');
        $cajero = $this->makeUser('Cajero', 'cajero@test.com');

        $this->assertTrue($mozo->canAccessPanel(app(\Filament\Panel::class)));
        $this->assertTrue($cajero->canAccessPanel(app(\Filament\Panel::class)));
    }

    // ─────────────────────────────────────────────────────────────
    // PANTALLA DE COCINA (KitchenDashboard::canAccess)
    // ─────────────────────────────────────────────────────────────

    public function test_cocinero_y_admin_acceden_a_cocina(): void
    {
        $admin = $this->makeUser('super_admin', 'admin@test.com');
        $cocinero = $this->makeUser('Cocinero', 'cocinero2@test.com');

        $this->actingAs($admin);
        $this->assertTrue(KitchenDashboard::canAccess());

        $this->actingAs($cocinero);
        $this->assertTrue(KitchenDashboard::canAccess());
    }

    public function test_mozo_y_cajero_no_acceden_a_pantalla_de_cocina(): void
    {
        $mozo = $this->makeUser('Mozo', 'mozo2@test.com');
        $cajero = $this->makeUser('Cajero', 'cajero2@test.com');

        $this->actingAs($mozo);
        $this->assertFalse(KitchenDashboard::canAccess());

        $this->actingAs($cajero);
        $this->assertFalse(KitchenDashboard::canAccess());
    }

    // ─────────────────────────────────────────────────────────────
    // SEEDER: permisos de tareas por rol
    // ─────────────────────────────────────────────────────────────

    public function test_seeder_asigna_permisos_de_tareas_por_rol(): void
    {
        $this->seed(\Database\Seeders\StaffSeeder::class);

        $mozo = User::where('email', 'mozo@moodi.com')->first();
        $cajero = User::where('email', 'cajero@moodi.com')->first();
        $cocinero = User::where('email', 'cocina@moodi.com')->first();

        $this->assertNotNull($mozo);
        $this->assertNotNull($cajero);
        $this->assertNotNull($cocinero);

        // Mozo: mesa + pedido + catálogo (ver).
        $this->assertTrue($mozo->hasRole('Mozo'));
        $this->assertTrue($mozo->can('view_any_table'));
        $this->assertTrue($mozo->can('view_any_order'));
        $this->assertTrue($mozo->can('create_order'));
        $this->assertTrue($mozo->can('view_product'));

        // Cajero: caja + ventas.
        $this->assertTrue($cajero->hasRole('Cajero'));
        $this->assertTrue($cajero->can('open_caja'));
        $this->assertTrue($cajero->can('view_any_sale'));
        $this->assertTrue($cajero->can('create_sale'));

        // Cocinero: cocina + catálogo lectura; NO puede operar ventas ni caja.
        $this->assertTrue($cocinero->hasRole('Cocinero'));
        $this->assertTrue($cocinero->can('view_any_product'));
        $this->assertTrue($cocinero->can('view_any_recipe'));
        $this->assertTrue($cocinero->can('view_any_ingredient'));
        $this->assertTrue($cocinero->can('view_any_order'));
        $this->assertFalse($cocinero->can('create_sale'));
        $this->assertFalse($cocinero->can('open_caja'));
        $this->assertFalse($cocinero->can('create_order'));
    }

    public function test_cocinero_no_ve_el_escritorio(): void
    {
        $cocinero = $this->makeUser('Cocinero', 'cocina3@test.com');

        // El Escritorio (dashboard general) queda oculto para el Cocinero.
        $this->actingAs($cocinero);
        $this->assertFalse(\App\Filament\Pages\Dashboard::canAccess());
    }

    public function test_mozo_y_admin_ven_el_escritorio(): void
    {
        $admin = $this->makeUser('super_admin', 'admin3@test.com');
        $mozo = $this->makeUser('Mozo', 'mozo3@test.com');

        $this->actingAs($admin);
        $this->assertTrue(\App\Filament\Pages\Dashboard::canAccess());

        $this->actingAs($mozo);
        $this->assertTrue(\App\Filament\Pages\Dashboard::canAccess());
    }

    public function test_reportes_registran_sub_paginas_con_parent(): void
    {
        $admin = $this->makeUser('super_admin', 'admin4@test.com');
        $this->actingAs($admin);

        $this->assertTrue(\App\Filament\Pages\ReporteVentas::canAccess());
        $this->assertTrue(\App\Filament\Pages\ReporteCaja::canAccess());
        $this->assertTrue(\App\Filament\Pages\ReporteProductos::canAccess());
        $this->assertTrue(\App\Filament\Pages\ReporteGanancias::canAccess());

        // Todas cuelgan del mismo item padre "Reportes".
        $this->assertSame('Reportes', \App\Filament\Pages\ReporteVentas::getNavigationParentItem());
        $this->assertSame('Reportes', \App\Filament\Pages\ReporteCaja::getNavigationParentItem());
        $this->assertSame('Reportes', \App\Filament\Pages\ReporteProductos::getNavigationParentItem());
        $this->assertSame('Reportes', \App\Filament\Pages\ReporteGanancias::getNavigationParentItem());
    }
}