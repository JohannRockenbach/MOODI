<?php

namespace Tests\Feature;

use App\Actions\Sales\AnnulSaleAction;
use App\Models\ActivityLog;
use App\Models\Caja;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\Table;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * TAREA 2.1 (OBJ-09 / CU-004.1) — AuditService + hooks de auditoría.
 * Testing Strategy B: happy path, seguridad (fallo silencioso) y edge cases.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function auditUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function auditPaidSale(User $owner): Sale
{
    $waiter = User::factory()->create(['restaurant_id' => 1]);

    $table = Table::create([
        'number' => '1',
        'capacity' => 4,
        'location' => 'Salón',
        'status' => 'available',
        'waiter_id' => $waiter->id,
        'restaurant_id' => 1,
    ]);

    $caja = Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $owner->id,
        'restaurant_id' => 1,
    ]);

    $order = Order::create([
        'status' => 'completed',
        'type' => 'local',
        'table_id' => $table->id,
        'waiter_id' => $waiter->id,
        'restaurant_id' => 1,
    ]);

    return Sale::create([
        'total_amount' => 1500,
        'payment_method' => 'cash',
        'status' => 'paid',
        'order_id' => $order->id,
        'cashier_id' => $owner->id,
        'restaurant_id' => 1,
        'caja_id' => $caja->id,
    ]);
}

// ─────────────────────────────────────────────────────────────
// 1) HAPPY PATH — anular una venta registra auditoría con user + motivo,
//    y la fila se guarda con el contexto esperado.
// ─────────────────────────────────────────────────────────────

it('registra sale.annulled con usuario y motivo al anular una venta', function () {
    $admin = auditUser('super_admin');
    $sale = auditPaidSale($admin);

    $this->actingAs($admin);

    app(AnnulSaleAction::class)($sale, $admin, 'Error de carga');

    $log = ActivityLog::where('action', 'sale.annulled')->latest('created_at')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->subject_type)->toBe(Sale::class)
        ->and($log->subject_id)->toBe($sale->id)
        ->and($log->context['motivo'])->toBe('Error de carga');
});

it('registra activity_log al crear una venta (sale.created) y al cerrar caja', function () {
    $admin = auditUser('super_admin');
    $this->actingAs($admin);

    $sale = auditPaidSale($admin);

    $saleLog = ActivityLog::where('action', 'sale.created')->latest('created_at')->first();

    expect($saleLog)->not->toBeNull()
        ->and($saleLog->subject_id)->toBe($sale->id)
        ->and((float) $saleLog->context['total_amount'])->toBe(1500.0)
        ->and($saleLog->context['caja_id'])->toBe($sale->caja_id);

    // Cerrar la caja (misma transición que CajaResource 'close').
    $caja = $sale->caja;
    $caja->update([
        'closing_date' => now(),
        'closing_user_id' => $admin->id,
        'status' => 'cerrada',
        'final_balance' => 1500,
        'total_sales' => 1500,
    ]);

    $closeLog = ActivityLog::where('action', 'caja.closed')->latest('created_at')->first();

    expect($closeLog)->not->toBeNull()
        ->and($closeLog->subject_id)->toBe($caja->id)
        ->and((float) $closeLog->context['final_balance'])->toBe(1500.0);
});

// ─────────────────────────────────────────────────────────────
// 2) SEGURIDAD — el registro fallido (error de DB en el insert) NO rompe
//    la operación original ni la transacción del llamador.
// ─────────────────────────────────────────────────────────────

it('no rompe la operación original si el insert de auditoría falla (savepoint)', function () {
    $admin = auditUser('super_admin');
    $this->actingAs($admin);

    // Forzar un error de DB REAL en cada insert a activity_log (trigger PG).
    DB::statement(
        "CREATE OR REPLACE FUNCTION public.fail_activity_log() RETURNS trigger AS \$\$
         BEGIN RAISE EXCEPTION 'simulated audit failure'; END;
         \$\$ LANGUAGE plpgsql"
    );
    DB::statement(
        'CREATE TRIGGER trg_fail_activity_log
         BEFORE INSERT ON activity_log
         FOR EACH ROW EXECUTE FUNCTION public.fail_activity_log()'
    );

    try {
        // La venta se crea DENTRO de una transacción y la auditoría
        // (sale.created) falla adentro: el savepoint la aísla.
        $sale = DB::transaction(fn () => Sale::create([
            'total_amount' => 900,
            'payment_method' => 'cash',
            'status' => 'paid',
            'order_id' => Order::create([
                'status' => 'pending',
                'type' => 'local',
                'waiter_id' => $admin->id,
                'restaurant_id' => 1,
            ])->id,
            'cashier_id' => $admin->id,
            'restaurant_id' => 1,
        ]));

        expect($sale)->not->toBeNull()
            ->and(Sale::find($sale->id))->not->toBeNull();

        // La llamada directa tampoco lanza excepción (fallo silencioso).
        expect(fn () => AuditService::record('sale.annulled', $sale, ['motivo' => 'x'], $admin))
            ->not->toThrow(\Throwable::class);
    } finally {
        DB::statement('DROP TRIGGER IF EXISTS trg_fail_activity_log ON activity_log');
        DB::statement('DROP FUNCTION IF EXISTS public.fail_activity_log()');
    }
});

// ─────────────────────────────────────────────────────────────
// 3) EDGE — sujeto null, usuario null (sin auth) y contexto sin secretos.
// ─────────────────────────────────────────────────────────────

it('registra con user_id null y subject null cuando no hay usuario ni sujeto', function () {
    AuditService::record('sale.restored', null, ['motivo_previo' => 'Error de carga']);

    $log = ActivityLog::latest('created_at')->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('sale.restored')
        ->and($log->user_id)->toBeNull()
        ->and($log->subject_type)->toBeNull()
        ->and($log->subject_id)->toBeNull()
        ->and($log->context['motivo_previo'])->toBe('Error de carga');
});

it('el contexto nunca incluye secretos (password/token/api key)', function () {
    $user = auditUser('Mozo'); // El alta de usuario dispara user.created.

    $log = ActivityLog::where('action', 'user.created')
        ->where('subject_id', $user->id)
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull();

    $json = json_encode($log->context);

    expect($json)
        ->not->toContain('password')
        ->not->toContain('token')
        ->not->toContain('api_key')
        ->not->toContain('secret');
});