<?php

namespace Tests\Feature;

use App\Filament\Pages\ReporteCaja;
use App\Filament\Pages\ReporteGanancias;
use App\Filament\Pages\ReporteProductos;
use App\Filament\Pages\ReporteVentas;
use App\Filament\Pages\Reports;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Restaurant::factory()->create(['id' => 1]);
    }

    private function makeAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');

        $user = User::factory()->create([
            'name' => 'Gonzalo',
            'email' => 'admin@moodi.com',
            'restaurant_id' => 1,
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_paginas_de_reportes_acceden_con_super_admin(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(Reports::getUrl())
            ->assertOk();

        $this->actingAs($admin)
            ->get(ReporteVentas::getUrl())
            ->assertOk();

        $this->actingAs($admin)
            ->get(ReporteCaja::getUrl())
            ->assertOk();

        $this->actingAs($admin)
            ->get(ReporteProductos::getUrl())
            ->assertOk();

        $this->actingAs($admin)
            ->get(ReporteGanancias::getUrl())
            ->assertOk();
    }

    public function test_paginas_de_reportes_deniegan_a_mozo(): void
    {
        Role::findOrCreate('Mozo', 'web');

        $mozo = User::factory()->create([
            'name' => 'Mozo',
            'email' => 'mozo@moodi.com',
            'restaurant_id' => 1,
        ]);
        $mozo->assignRole('Mozo');

        $this->actingAs($mozo)
            ->get(Reports::getUrl())
            ->assertForbidden();

        $this->actingAs($mozo)
            ->get(ReporteVentas::getUrl())
            ->assertForbidden();
    }
}