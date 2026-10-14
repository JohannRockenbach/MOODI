<?php

namespace Tests\Feature;

use App\Filament\Pages\CrearPedido;
use App\Models\Caja;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * TAREA 2 (P0 — feature): QR de pago al cobrar (futura actualización).
 * El QR codifica una URL interna opaca (/pago-qr/{token}); NO procesa
 * pagos y la ruta pública solo muestra el aviso, sin datos de la venta.
 *
 * Testing Strategy B: happy path (cobro genera QR /pago-qr), seguridad
 * (token malformado → 404, sin datos revelados) y edge case (sin cuenta
 * no se genera QR; cobro bloqueado no deja QR post-cobro).
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);

    foreach (['super_admin', 'Mozo', 'Cajero', 'Cocinero'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function qrTestUser(string $role): User
{
    $user = User::factory()->create(['restaurant_id' => 1]);
    $user->assignRole($role);

    return $user;
}

function qrTestOpenCaja(User $user): Caja
{
    return Caja::create([
        'opening_date' => now(),
        'initial_balance' => 0,
        'status' => 'abierta',
        'opening_user_id' => $user->id,
        'restaurant_id' => 1,
    ]);
}

function qrTestTable(): Table
{
    return Table::factory()->create([
        'number' => 61,
        'capacity' => 4,
        'location' => 'Salón',
        'status' => Table::STATUS_OCCUPIED,
        'restaurant_id' => 1,
    ]);
}

function qrTestProduct(string $name, float $price, float $stock = 50): Product
{
    $category = Category::firstOrCreate(['name' => 'Bebidas']);

    return Product::factory()->create([
        'name' => $name,
        'price' => $price,
        'is_available' => true,
        'category_id' => $category->id,
        'restaurant_id' => 1,
        'recipe_id' => null,
        'stock' => $stock,
    ]);
}

function qrTestDraftItem(Product $product, int $qty, string $note = ''): array
{
    return [
        'product_id' => $product->id,
        'name' => $product->name,
        'price' => (float) $product->price,
        'qty' => $qty,
        'note' => $note,
        'section_key' => 'bebidas',
        'section_label' => '🥤 Bebidas',
    ];
}

// ─────────────────────────────────────────────────────────────
// 1) HAPPY PATH — ruta pública válida muestra el aviso sin datos
// ─────────────────────────────────────────────────────────────

it('responde 200 con el aviso para un token con formato valido y no revela datos', function () {
    // Token opaco con formato correcto (sha256, 64 hex) — sin crear venta.
    $token = hash('sha256', 'sale-prueba|2026-01-01');

    $this->get('/pago-qr/'.$token)
        ->assertOk()
        ->assertSee('futura actualización', false)
        ->assertSee('Pago por QR', false)
        ->assertDontSee('Monto')
        ->assertDontSee('$');
});

// ─────────────────────────────────────────────────────────────
// 2) SEGURIDAD — token malformado → 404 y sin datos de operación
// ─────────────────────────────────────────────────────────────

it('responde 404 para tokens malformados (no hex, largo invalido o mayusculas)', function () {
    // No es sha256 hex.
    $this->get('/pago-qr/not-a-token')->assertNotFound();

    // Largo inválido: 63 y 65 hex.
    $this->get('/pago-qr/'.str_repeat('a', 63))->assertNotFound();
    $this->get('/pago-qr/'.str_repeat('a', 65))->assertNotFound();

    // Mayúsculas: el formato exigido es [a-f0-9] minúsculas.
    $this->get('/pago-qr/'.str_repeat('A', 64))->assertNotFound();

    // Caracteres hex válidos pero con símbolos mezclados.
    $this->get('/pago-qr/'.str_repeat('a', 62).'g1')->assertNotFound();
});

// ─────────────────────────────────────────────────────────────
// 3) HAPPY PATH (flujo) — al cobrar se genera el QR con URL /pago-qr
// ─────────────────────────────────────────────────────────────

it('al cobrar genera el QR con URL interna /pago-qr y el modal lo muestra con el monto cobrado', function () {
    $mozo = qrTestUser('Mozo');
    qrTestOpenCaja($mozo);
    $table = qrTestTable();
    $coca = qrTestProduct('Coca QR Test', 500.0);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);
    $component->set('selectedTableId', $table->id)
        ->set('items', [qrTestDraftItem($coca, 2)]) // 1000
        ->call('abrirCobro');

    expect($component->get('open'))->toBeTrue();

    // Pre-cobro: "Generar QR" revela el QR con el total.
    $component->call('generatePaymentQr');

    expect($component->get('paymentQrRevealed'))->toBeTrue()
        ->and($component->get('paymentQrTotal'))->toBe(1000.0)
        ->and($component->get('paymentQrToken'))->toMatch('/^[a-f0-9]{64}$/')
        ->and($component->get('paymentQrImage'))->toContain('data:image/png;base64')
        ->and($component->get('paymentQrConfirmed'))->toBeFalse();

    // Cobrar: el QR queda CONFIRMADO con el monto cobrado y URL /pago-qr real.
    $component->set('payments.0.method', 'card')->call('cobrar')->assertHasNoErrors();

    $token = $component->get('paymentQrToken');

    expect($component->get('paymentQrConfirmed'))->toBeTrue()
        ->and($component->get('paymentQrRevealed'))->toBeTrue()
        ->and($component->get('paymentQrTotal'))->toBe(1000.0)
        ->and($token)->toMatch('/^[a-f0-9]{64}$/')
        ->and($component->get('paymentQrImage'))->toContain('data:image/png;base64')
        ->and(url('/pago-qr/'.$token))->toContain('/pago-qr/'.$token);

    // La URL del QR responde 200 (aviso de futura actualización).
    $this->get('/pago-qr/'.$token)->assertOk()->assertSee('futura actualización', false);
});

// ─────────────────────────────────────────────────────────────
// 4) EDGE CASE — sin cuenta no se genera QR; cobro bloqueado no lo fija
// ─────────────────────────────────────────────────────────────

it('no genera QR sin cuenta seleccionada ni lo fija cuando el cobro falla', function () {
    $mozo = qrTestUser('Mozo');
    qrTestOpenCaja($mozo);
    $table = qrTestTable();
    $coca = qrTestProduct('Coca QR Edge', 500.0);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);

    // (a) Sin cuenta (modal de selección) → Generar QR no revela nada.
    $component->set('selectedTableId', null)->call('abrirCobro');
    $component->call('generatePaymentQr');

    expect($component->get('paymentQrRevealed'))->toBeFalse()
        ->and($component->get('paymentQrConfirmed'))->toBeFalse()
        ->and($component->get('paymentQrImage'))->toBeNull();

    // (b) Cobro con split inválido (suma != total) → venta NO creada y el
    //     modal NO fija QR post-cobro.
    $component->set('selectedTableId', $table->id)
        ->set('items', [qrTestDraftItem($coca, 2)])
        ->call('abrirCobro')
        ->set('payments.0.method', 'card')->set('payments.0.amount', '500') // 500 != 1000
        ->call('cobrar');

    expect($component->get('paymentQrConfirmed'))->toBeFalse()
        ->and($component->get('paymentQrRevealed'))->toBeFalse()
        ->and(\App\Models\Sale::count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────
// 5) HAPPY PATH — QR como MÉTODO DE PAGO (TAREA 3): split con 'qr'
// ─────────────────────────────────────────────────────────────

it('cobra con método qr: la Sale se registra con payment_method qr y el modal muestra el QR con el monto real', function () {
    $mozo = qrTestUser('Mozo');
    qrTestOpenCaja($mozo);
    $table = qrTestTable();
    $coca = qrTestProduct('Coca QR Metodo', 500.0);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);
    $component->set('selectedTableId', $table->id)
        ->set('items', [qrTestDraftItem($coca, 2)]) // 1000
        ->call('abrirCobro')
        ->set('payments.0.method', 'qr')
        ->call('cobrar')
        ->assertHasNoErrors();

    // La venta se registró con el método Qr (dominante del split).
    $sale = \App\Models\Sale::first();

    expect($sale)->not->toBeNull()
        ->and($sale->payment_method)->toBe('qr')
        ->and((float) $sale->total_amount)->toBe(1000.0);

    // Desglose: una fila qr con el total.
    $payment = $sale->payments()->first();

    expect($payment)->not->toBeNull()
        ->and($payment->payment_method)->toBe('qr')
        ->and((float) $payment->amount)->toBe(1000.0);

    // Post-cobro: el modal queda abierto en la vista QR CONFIRMADO con el
    // monto REAL de la venta y el mensaje de éxito visible.
    expect($component->get('open'))->toBeTrue()
        ->and($component->get('paymentQrConfirmed'))->toBeTrue()
        ->and($component->get('paymentQrTotal'))->toBe(1000.0)
        ->and($component->get('paymentQrImage'))->toContain('data:image/png;base64')
        ->and($component->html())->toContain('Cuenta cobrada')
        ->and($component->html())->toContain('Venta registrada correctamente.');

    // La URL pública del QR sigue respondiendo el aviso (sin datos).
    $this->get('/pago-qr/'.$component->get('paymentQrToken'))->assertOk();
});

// ─────────────────────────────────────────────────────────────
// 6) EDGE CASE — split QR + efectivo: dominante y desglose completo
// ─────────────────────────────────────────────────────────────

it('divide un cobro entre QR y efectivo: la Sale guarda el método dominante y el desglose completo', function () {
    $mozo = qrTestUser('Mozo');
    qrTestOpenCaja($mozo);
    $table = qrTestTable();
    $coca = qrTestProduct('Coca QR Split', 1000.0);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);
    $component->set('selectedTableId', $table->id)
        ->set('items', [qrTestDraftItem($coca, 1)]) // 1000
        ->call('abrirCobro')
        ->set('payments', [
            ['method' => 'qr', 'amount' => '600'],
            ['method' => 'cash', 'amount' => '400'],
        ])
        ->set('montoRecibido', '400')
        ->call('cobrar')
        ->assertHasNoErrors();

    $sale = \App\Models\Sale::first();

    expect($sale)->not->toBeNull()
        ->and($sale->payment_method)->toBe('qr') // dominante: 600 > 400
        ->and((float) $sale->total_amount)->toBe(1000.0);

    $desglose = $sale->payments()->orderBy('id')->get();

    expect($desglose)->toHaveCount(2)
        ->and($desglose[0]->payment_method)->toBe('qr')
        ->and((float) $desglose[0]->amount)->toBe(600.0)
        ->and($desglose[1]->payment_method)->toBe('cash')
        ->and((float) $desglose[1]->amount)->toBe(400.0);

    expect($component->get('paymentQrConfirmed'))->toBeTrue()
        ->and($component->get('paymentQrTotal'))->toBe(1000.0);
});

// ─────────────────────────────────────────────────────────────
// 7) EDGE CASE — botón "Cerrar" de la vista QR cierra y limpia
// ─────────────────────────────────────────────────────────────

it('el botón Cerrar de la vista QR confirmado cierra el modal y limpia el QR', function () {
    $mozo = qrTestUser('Mozo');
    qrTestOpenCaja($mozo);
    $table = qrTestTable();
    $coca = qrTestProduct('Coca QR Cerrar', 250.0);

    $component = Livewire::actingAs($mozo)->test(CrearPedido::class);
    $component->set('selectedTableId', $table->id)
        ->set('items', [qrTestDraftItem($coca, 1)])
        ->call('abrirCobro')
        ->set('payments.0.method', 'qr')
        ->call('cobrar')
        ->assertHasNoErrors();

    expect($component->get('paymentQrConfirmed'))->toBeTrue();

    $component->call('closeQr');

    expect($component->get('open'))->toBeFalse()
        ->and($component->get('paymentQrConfirmed'))->toBeFalse()
        ->and($component->get('paymentQrRevealed'))->toBeFalse()
        ->and($component->get('paymentQrImage'))->toBeNull()
        ->and($component->get('paymentQrToken'))->toBeNull();
});
