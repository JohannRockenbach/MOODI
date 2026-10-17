<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CU-009.7 / CU-009.8 — Botones de escalamiento del cliente en el menú
 * público (cart-panel): "Llamar al mozo" y "Llamar al administrador".
 *
 * Testing Strategy B:
 * - happy: requestWaiter notifica por DB a super_admin + Mozo; requestAdmin
 *   notifica SOLO a super_admin.
 * - seguridad: la notificación NO llega a Cocinero/Cajero (solo roles
 *   atendidos); funciona sin autenticación (menú anónimo).
 * - edge: sin super_admin no rompe (no lanza); llamar dos veces crea dos
 *   notificaciones (sin rate-limit).
 */
class ClientEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Restaurant::factory()->create(['id' => 1]);

        foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero', 'cliente'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create([
            'email' => strtolower($role).'-'.uniqid().'@test.com',
            'restaurant_id' => 1,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_request_waiter_notifica_a_super_admin_y_mozo(): void
    {
        $admin = $this->makeUser('super_admin');
        $mozo = $this->makeUser('Mozo');

        Livewire::test('cart-panel')->call('requestWaiter');

        $this->assertDatabaseCount('notifications', 2);

        $ids = DatabaseNotification::pluck('notifiable_id')->all();

        $this->assertContains($admin->id, $ids);
        $this->assertContains($mozo->id, $ids);
        $this->assertSame('🛎 Cliente solicita atención del mozo', DatabaseNotification::first()->data['title']);
    }

    public function test_request_admin_notifica_solo_a_super_admin(): void
    {
        $admin = $this->makeUser('super_admin');
        $mozo = $this->makeUser('Mozo');

        Livewire::test('cart-panel')->call('requestAdmin');

        $this->assertDatabaseCount('notifications', 1);

        $notification = DatabaseNotification::first();

        $this->assertSame($admin->id, $notification->notifiable_id);
        $this->assertNotSame($mozo->id, $notification->notifiable_id);
        $this->assertSame('🛎 Cliente solicita al administrador', $notification->data['title']);
    }

    public function test_no_notifica_a_cocinero_ni_cajero(): void
    {
        $admin = $this->makeUser('super_admin');
        $cocinero = $this->makeUser('Cocinero');
        $cajero = $this->makeUser('Cajero');

        Livewire::test('cart-panel')->call('requestWaiter');
        Livewire::test('cart-panel')->call('requestAdmin');

        $ids = DatabaseNotification::pluck('notifiable_id')->all();

        $this->assertContains($admin->id, $ids);
        $this->assertNotContains($cocinero->id, $ids);
        $this->assertNotContains($cajero->id, $ids);
    }

    public function test_funciona_sin_autenticacion(): void
    {
        $this->makeUser('super_admin');

        // Sin actingAs: el componente corre como visitante anónimo.
        Livewire::test('cart-panel')->call('requestAdmin');

        $this->assertFalse(auth()->check());
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_sin_super_admin_no_rompe(): void
    {
        // No hay super_admin ni Mozo: no lanza y no deja notificaciones.
        Livewire::test('cart-panel')
            ->call('requestAdmin')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_llamar_dos_veces_crea_dos_notificaciones(): void
    {
        $this->makeUser('super_admin');

        Livewire::test('cart-panel')->call('requestAdmin');
        Livewire::test('cart-panel')->call('requestAdmin');

        $this->assertDatabaseCount('notifications', 2);
    }
}
