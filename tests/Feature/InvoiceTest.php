<?php

use App\Actions\Sales\IssueInvoiceAction;
use App\Filament\Resources\SaleResource\Pages\ListSales;
use App\Models\ActivityLog;
use App\Models\Caja;
use App\Models\Category;
use App\Models\Cliente;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\Table;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CU-001.12 — Comprobante de venta básico (Invoice).
 * Testing Strategy B: happy path, seguridad/autorización y edge cases.
 */

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function invoiceTestUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function invoiceTestPaidSale(User $owner, array $overrides = []): Sale
{
    $waiter = User::factory()->create(['restaurant_id' => 1]);

    $table = Table::create([
        'number' => (string) uniqid(),
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

    return Sale::create(array_merge([
        'total_amount' => 1500,
        'payment_method' => 'cash',
        'status' => 'paid',
        'order_id' => $order->id,
        'cashier_id' => $owner->id,
        'restaurant_id' => 1,
        'caja_id' => $caja->id,
    ], $overrides));
}

// ─────────────────────────────────────────────────────────────
// 1) HAPPY PATH — super_admin emite comprobantes correlativos.
// ─────────────────────────────────────────────────────────────

it('emite comprobante correlativo para ventas pagadas: 0001-00000001 y 0001-00000002', function () {
    $admin = invoiceTestUser('super_admin');

    $first = invoiceTestPaidSale($admin);
    $second = invoiceTestPaidSale($admin);

    $invoiceA = app(IssueInvoiceAction::class)($first, $admin);
    $invoiceB = app(IssueInvoiceAction::class)($second, $admin);

    expect($invoiceA->cae_number)->toBe('0001-00000001')
        ->and($invoiceB->cae_number)->toBe('0001-00000002')
        ->and($invoiceA->sale_id)->toBe($first->id)
        ->and($invoiceB->sale_id)->toBe($second->id)
        ->and($invoiceA->customer_data)->toBe([]);

    // La relación inversa queda visible desde la venta.
    expect($first->fresh()->invoice->cae_number)->toBe('0001-00000001');
});

it('guarda datos del cliente y registra auditoría invoice.issued', function () {
    $admin = invoiceTestUser('super_admin');
    $this->actingAs($admin);

    $sale = invoiceTestPaidSale($admin);

    $cliente = Cliente::create([
        'restaurant_id' => 1,
        'name' => 'María López',
        'email' => 'maria@example.com',
        'phone' => '11-5555-1234',
    ]);
    $sale->order->update(['customer_id' => $cliente->id]);

    $invoice = app(IssueInvoiceAction::class)($sale->fresh(), $admin);

    expect($invoice->customer_data['name'])->toBe('María López')
        ->and($invoice->customer_data['email'])->toBe('maria@example.com');

    $log = ActivityLog::where('action', 'invoice.issued')
        ->where('subject_id', $invoice->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->context['sale_id'])->toBe($sale->id)
        ->and($log->context['number'])->toBe('0001-00000001');
});

it('permite a super_admin emitir desde el listado y ver el comprobante', function () {
    $admin = invoiceTestUser('super_admin');
    $sale = invoiceTestPaidSale($admin);

    Livewire::actingAs($admin)
        ->test(ListSales::class)
        ->assertTableActionVisible('emitir_comprobante', $sale)
        ->callTableAction('emitir_comprobante', $sale)
        ->assertHasNoTableActionErrors();

    expect(Invoice::count())->toBe(1)
        ->and($sale->fresh()->invoice->cae_number)->toBe('0001-00000001');

    // Tras emitir, la acción de emisión se oculta y aparece "Ver comprobante".
    // Se usa fresh(): el objeto $sale previo cacheó la relación invoice como null.
    $saleFresh = $sale->fresh();

    Livewire::actingAs($admin)
        ->test(ListSales::class)
        ->assertTableActionHidden('emitir_comprobante', $saleFresh)
        ->assertTableActionVisible('ver_comprobante', $saleFresh);
});

// ─────────────────────────────────────────────────────────────
// 2) SEGURIDAD — solo super_admin emite comprobante.
// ─────────────────────────────────────────────────────────────

it('deniega la emisión a Cajero y Mozo (AuthorizationException / 403)', function () {
    $sale = invoiceTestPaidSale(invoiceTestUser('super_admin'));

    $cajero = invoiceTestUser('Cajero');
    $mozo = invoiceTestUser('Mozo');

    expect(fn () => app(IssueInvoiceAction::class)($sale, $cajero))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(IssueInvoiceAction::class)($sale, $mozo))
        ->toThrow(AuthorizationException::class);

    expect(Invoice::count())->toBe(0);
});

it('un Mozo no monta el listado de ventas (403) y un Cajero no fuerza la acción (403 en profundidad)', function () {
    $admin = invoiceTestUser('super_admin');
    $sale = invoiceTestPaidSale($admin);

    // (a) Mozo: viewAny() lo excluye → 403 al montar.
    $mozo = invoiceTestUser('Mozo');
    Livewire::actingAs($mozo)->test(ListSales::class)->assertStatus(403);

    // (b) Cajero: puede montar, ve la acción oculta, y forzarla aborta 403.
    $cajero = invoiceTestUser('Cajero');
    Livewire::actingAs($cajero)
        ->test(ListSales::class)
        ->assertTableActionHidden('emitir_comprobante', $sale);

    $component = Livewire::actingAs($cajero)->test(ListSales::class);
    $action = $component->instance()->getTable()->getAction('emitir_comprobante');
    $action->record($sale);

    $this->actingAs($cajero);
    $this->expectException(HttpException::class);
    $action->call(['record' => $sale]);

    expect(Invoice::count())->toBe(0);
});

it('no permite emitir comprobante para una venta anulada', function () {
    $admin = invoiceTestUser('super_admin');

    $sale = invoiceTestPaidSale($admin);
    $sale->update(['status' => 'annulled', 'annulled_at' => now()]);

    expect(fn () => app(IssueInvoiceAction::class)($sale->fresh(), $admin))
        ->toThrow(\DomainException::class, 'La venta no está pagada');

    expect(Invoice::count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────
// 3) EDGE CASES — duplicados y correlativos.
// ─────────────────────────────────────────────────────────────

it('no emite un comprobante duplicado: guard de la acción + UNIQUE(sale_id)', function () {
    $admin = invoiceTestUser('super_admin');
    $sale = invoiceTestPaidSale($admin);

    app(IssueInvoiceAction::class)($sale, $admin);

    // (a) La acción rechaza una segunda emisión sobre la misma venta.
    expect(fn () => app(IssueInvoiceAction::class)($sale->fresh(), $admin))
        ->toThrow(\DomainException::class, 'ya tiene un comprobante');

    // (b) Forzar el create directo (bypass del guard) viola UNIQUE(sale_id).
    // Se aísla en un savepoint: en Postgres la violación abortaría la transacción
    // de RefreshDatabase y las consultas posteriores fallarían.
    $uniqueViolated = false;

    try {
        DB::transaction(fn () => Invoice::create([
            'cae_number' => '0001-99999999',
            'customer_data' => [],
            'sale_id' => $sale->id,
        ]));
    } catch (QueryException) {
        $uniqueViolated = true;
    }

    expect($uniqueViolated)->toBeTrue()
        ->and(Invoice::count())->toBe(1);
});

it('genera correlativos sin colisión dentro del mismo año', function () {
    $admin = invoiceTestUser('super_admin');

    $numbers = collect(range(1, 5))->map(function () use ($admin) {
        $sale = invoiceTestPaidSale($admin);

        return app(IssueInvoiceAction::class)($sale, $admin)->cae_number;
    });

    expect($numbers->unique()->count())->toBe(5)
        ->and($numbers->toArray())->toBe([
            '0001-00000001',
            '0001-00000002',
            '0001-00000003',
            '0001-00000004',
            '0001-00000005',
        ]);
});

it('renderiza el comprobante sin errores con detalle de ítems y total', function () {
    $admin = invoiceTestUser('super_admin');
    $sale = invoiceTestPaidSale($admin);

    $category = Category::firstOrCreate(['name' => 'Bebidas']);
    $product = Product::factory()->create([
        'restaurant_id' => 1,
        'name' => 'Café',
        'price' => 500,
        'is_available' => true,
        'category_id' => $category->id,
        'recipe_id' => null,
        'stock' => 50,
    ]);
    $sale->order->orderProducts()->create([
        'product_id' => $product->id,
        'quantity' => 3,
        'price' => 500,
    ]);

    $invoice = app(IssueInvoiceAction::class)($sale, $admin);

    $html = view('filament.sales.invoice-modal', [
        'invoice' => $invoice,
        'sale' => $sale->loadMissing(['order', 'restaurant', 'caja']),
    ])->render();

    expect($html)
        ->toContain($invoice->cae_number)
        ->toContain('Café')
        ->toContain('1.500,00');
});
