<?php

namespace Tests\Feature;

use App\Filament\Pages\Auditoria;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * TAREA 2.1 (OBJ-09 / CU-004.1) — página Auditoria (solo super_admin).
 * Testing Strategy B: happy path (UI lista la fila), seguridad (403 a otros
 * roles) y edge (filtros conviven con filas sin contexto).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function auditPageUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

// ─────────────────────────────────────────────────────────────
// 1) HAPPY PATH — super_admin accede a la página y la fila de auditoría
//    se muestra con el label amigable en español.
// ─────────────────────────────────────────────────────────────

it('permite a super_admin ver la página y mostrar filas auditadas', function () {
    $admin = auditPageUser('super_admin');

    AuditService::record(
        'sale.annulled',
        null,
        ['motivo' => 'Error de carga'],
        $admin
    );

    $this->actingAs($admin)
        ->get(Auditoria::getUrl())
        ->assertOk()
        ->assertSee('Auditoría')
        ->assertSee('Venta anulada')
        ->assertSee('Error de carga');
});

// ─────────────────────────────────────────────────────────────
// 2) SEGURIDAD — Mozo / Cajero / Cocinero NO pueden acceder (403).
// ─────────────────────────────────────────────────────────────

it('niega el acceso a Mozo, Cajero y Cocinero (403)', function (string $role) {
    $user = auditPageUser($role);

    $this->actingAs($user)
        ->get(Auditoria::getUrl())
        ->assertForbidden();
})->with(['Mozo', 'Cajero', 'Cocinero']);

// ─────────────────────────────────────────────────────────────
// 3) EDGE — la página tolera filas sin usuario, sin sujeto y sin contexto.
// ─────────────────────────────────────────────────────────────

it('renderiza filas con usuario/sujeto/contexto nulos sin romperse', function () {
    $admin = auditPageUser('super_admin');

    AuditService::record('sale.restored'); // todo null

    $this->actingAs($admin)
        ->get(Auditoria::getUrl())
        ->assertOk()
        ->assertSee('Venta restaurada');
});