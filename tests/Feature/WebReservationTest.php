<?php

use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero', 'cliente'] as $role) {
        \Spatie\Permission\Models\Role::findOrCreate($role, 'web');
    }
});

function webReservationUser(string $role = 'cliente'): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function webReservationTable(array $overrides = []): Table
{
    return Table::factory()->create(array_merge([
        'number' => '10',
        'capacity' => 4,
        'location' => 'Salón Principal',
        'status' => Table::STATUS_AVAILABLE,
        'restaurant_id' => 1,
    ], $overrides));
}

function webReservationFecha(int $hours = 3): string
{
    return now()->addHours($hours)->format('Y-m-d\TH:i');
}

// ─────────────────────────────────────────────────────────────
// HAPPY PATH
// ─────────────────────────────────────────────────────────────

it('el cliente logueado reserva una mesa libre con capacidad suficiente', function () {
    $cliente = webReservationUser('cliente');
    $mesa = webReservationTable(['number' => '1', 'capacity' => 4]);

    $component = Volt::actingAs($cliente)
        ->test('pages.reservar')
        ->set('fecha', webReservationFecha(3))
        ->set('comensales', 4);

    // La mesa libre con capacidad suficiente aparece en el listado.
    $component->assertSee('Mesa #1');

    $component
        ->set('mesaId', $mesa->id)
        ->call('reservar')
        ->assertHasNoErrors()
        ->assertSee('Reserva registrada')
        ->assertSee('Mesa #1');

    expect(Reservation::count())->toBe(1)
        ->and(Reservation::first()->status)->toBe('pending')
        ->and(Reservation::first()->customer_id)->toBe($cliente->id)
        ->and(Reservation::first()->restaurant_id)->toBe(1)
        ->and(Reservation::first()->table_id)->toBe($mesa->id)
        ->and(Reservation::first()->guest_count)->toBe(4);
});

it('super_admin también puede acceder y reservar (para pruebas)', function () {
    $admin = webReservationUser('super_admin');
    $mesa = webReservationTable(['number' => '1', 'capacity' => 2]);

    Volt::actingAs($admin)
        ->test('pages.reservar')
        ->set('fecha', webReservationFecha(3))
        ->set('comensales', 2)
        ->set('mesaId', $mesa->id)
        ->call('reservar')
        ->assertHasNoErrors();

    expect(Reservation::count())->toBe(1)
        ->and(Reservation::first()->customer_id)->toBe($admin->id);
});

// ─────────────────────────────────────────────────────────────
// SEGURIDAD / AUTORIZACIÓN
// ─────────────────────────────────────────────────────────────

it('redirige al login al usuario no autenticado', function () {
    $this->get('/reservar')->assertRedirect(route('login'));
});

it('deniega con 403 a roles que no son cliente ni super_admin', function () {
    foreach (['Mozo', 'Cajero', 'Cocinero'] as $role) {
        $user = webReservationUser($role);

        Volt::actingAs($user)
            ->test('pages.reservar')
            ->assertStatus(403);
    }
});

it('no lista mesas ocupadas ni en mantenimiento y create las rechaza', function () {
    $cliente = webReservationUser('cliente');
    webReservationTable(['number' => '1', 'capacity' => 2]);
    $ocupada = webReservationTable(['number' => '2', 'capacity' => 2, 'status' => Table::STATUS_OCCUPIED]);
    $mantenimiento = webReservationTable(['number' => '3', 'capacity' => 2, 'status' => Table::STATUS_MAINTENANCE]);

    $component = Volt::actingAs($cliente)
        ->test('pages.reservar')
        ->set('fecha', webReservationFecha(3))
        ->set('comensales', 2);

    $component
        ->assertSee('Mesa #1')
        ->assertDontSee('Mesa #2')
        ->assertDontSee('Mesa #3');

    // create también rechaza la mesa ocupada (autoridad = ReservationService).
    expect(fn () => app(App\Services\ReservationService::class)->create([
        'restaurant_id' => 1,
        'customer_id' => $cliente->id,
        'table_id' => $ocupada->id,
        'reservation_time' => now()->addHours(3),
        'guest_count' => 2,
        'status' => 'pending',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(fn () => app(App\Services\ReservationService::class)->create([
        'restaurant_id' => 1,
        'customer_id' => $cliente->id,
        'table_id' => $mantenimiento->id,
        'reservation_time' => now()->addHours(3),
        'guest_count' => 2,
        'status' => 'pending',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(Reservation::count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────
// EDGE CASES
// ─────────────────────────────────────────────────────────────

it('no lista mesa con capacidad menor a los comensales', function () {
    $cliente = webReservationUser('cliente');
    webReservationTable(['number' => '1', 'capacity' => 2]);
    webReservationTable(['number' => '2', 'capacity' => 6]);

    Volt::actingAs($cliente)
        ->test('pages.reservar')
        ->set('fecha', webReservationFecha(3))
        ->set('comensales', 5)
        ->assertSee('Mesa #2')
        ->assertDontSee('Mesa #1');
});

it('rechaza comensales que exceden la capacidad de la mesa', function () {
    $cliente = webReservationUser('cliente');
    $mesa = webReservationTable(['number' => '1', 'capacity' => 4]);

    Volt::actingAs($cliente)
        ->test('pages.reservar')
        ->set('fecha', webReservationFecha(3))
        ->set('comensales', 6)
        ->set('mesaId', $mesa->id)
        ->call('reservar')
        ->assertHasErrors(['comensales']);

    expect(Reservation::count())->toBe(0);
});

it('no lista mesa con reserva activa solapada (±2h) aunque figure disponible', function () {
    $cliente = webReservationUser('cliente');
    $mesa = webReservationTable(['number' => '1', 'capacity' => 2]);

    Reservation::factory()->create([
        'table_id' => $mesa->id,
        'restaurant_id' => 1,
        'customer_id' => User::factory()->create()->id,
        'status' => 'pending',
        'reservation_time' => now()->addHours(3),
    ]);

    // El ReservationObserver marcó la mesa 'reserved'; forzamos 'available'
    // para aislar la regla de solapamiento del listado (status no es el
    // motivo de exclusión aquí).
    $mesa->fill(['status' => Table::STATUS_AVAILABLE])->saveQuietly();

    Volt::actingAs($cliente)
        ->test('pages.reservar')
        ->set('fecha', webReservationFecha(4))
        ->set('comensales', 2)
        ->assertDontSee('Mesa #1');
});

it('rechaza solapamiento ±2h al enviar el formulario', function () {
    $cliente = webReservationUser('cliente');
    $mesa = webReservationTable(['number' => '1', 'capacity' => 2]);

    Reservation::factory()->create([
        'table_id' => $mesa->id,
        'restaurant_id' => 1,
        'customer_id' => User::factory()->create()->id,
        'status' => 'pending',
        'reservation_time' => now()->addHours(3),
    ]);

    $mesa->fill(['status' => Table::STATUS_AVAILABLE])->saveQuietly();

    Volt::actingAs($cliente)
        ->test('pages.reservar')
        ->set('fecha', webReservationFecha(4))
        ->set('comensales', 2)
        ->set('mesaId', $mesa->id)
        ->call('reservar')
        ->assertHasErrors(['fecha']);

    // Solo queda la reserva preexistente.
    expect(Reservation::count())->toBe(1);
});

it('rechaza fecha pasada con error de validación', function () {
    $cliente = webReservationUser('cliente');
    $mesa = webReservationTable(['number' => '1', 'capacity' => 2]);

    Volt::actingAs($cliente)
        ->test('pages.reservar')
        ->set('fecha', now()->subHour()->format('Y-m-d\TH:i'))
        ->set('comensales', 2)
        ->set('mesaId', $mesa->id)
        ->call('reservar')
        ->assertHasErrors(['fecha']);

    expect(Reservation::count())->toBe(0);
});
