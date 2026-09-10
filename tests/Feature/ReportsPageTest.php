<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeRestaurant(int $id): Restaurant
    {
        return Restaurant::factory()->create(['id' => $id]);
    }

    private function makeSale(Restaurant $restaurant, float $amount, string $method = 'cash', ?string $status = 'paid'): Sale
    {
        $table = Table::factory()->create(['restaurant_id' => $restaurant->id]);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'completed',
            'type' => 'salon',
            'table_id' => $table->id,
        ]);

        $product = Product::factory()->create([
            'name' => 'Producto Reporte '.uniqid(),
            'restaurant_id' => $restaurant->id,
            'price' => $amount,
            'stock' => 100,
        ]);

        OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $amount,
        ]);

        return Sale::query()->create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'total_amount' => $amount,
            'payment_method' => $method,
            'status' => $status,
            'sale_date' => now(),
        ]);
    }

    private function makeReport(array $state = []): \App\Filament\Pages\ReporteVentas
    {
        $page = new \App\Filament\Pages\ReporteVentas();

        foreach ($state as $key => $value) {
            $page->{$key} = $value;
        }

        return $page;
    }

    // ─────────────────────────────────────────────────────────────
    // HAPPY PATH — cálculo correcto
    // ─────────────────────────────────────────────────────────────

    public function test_reporte_suma_ventas_del_rango_y_devuelve_metricas(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $this->makeSale($restaurant, 1000, 'cash');
        $this->makeSale($restaurant, 500, 'card');

        $report = $this->makeReport()->getReportData();

        $this->assertSame(1500.0, $report['total_amount']);
        $this->assertSame(2, $report['count']);
        $this->assertSame(750.0, $report['average']);
    }

    // ─────────────────────────────────────────────────────────────
    // SEGURIDAD / AISLAMIENTO — anuladas y otros tenants excluidos
    // ─────────────────────────────────────────────────────────────

    public function test_reporte_excluye_ventas_anuladas_por_defecto(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $this->makeSale($restaurant, 1000, 'cash', 'paid');

        $annulled = $this->makeSale($restaurant, 9999, 'card', 'paid');
        $annulled->annul(\App\Models\User::factory()->create(), 'test');

        $report = $this->makeReport()->getReportData();

        $this->assertSame(1000.0, $report['total_amount']);
        $this->assertSame(1, $report['count']);
    }

    public function test_reporte_ignora_ventas_de_otro_restaurante(): void
    {
        $this->makeRestaurant(1);
        $r2 = $this->makeRestaurant(2);
        $this->makeSale($r2, 99999, 'cash');

        $report = $this->makeReport()->getReportData();

        $this->assertSame(0.0, $report['total_amount']);
        $this->assertSame(0, $report['count']);
    }

    public function test_reporte_incluye_anuladas_si_se_marca_include_annulled(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $this->makeSale($restaurant, 1000, 'cash', 'paid');

        $annulled = $this->makeSale($restaurant, 500, 'cash', 'paid');
        $annulled->annul(\App\Models\User::factory()->create(), 'test');

        $report = $this->makeReport(["includeAnnulled" => true])->getReportData();

        $this->assertSame(1000.0, $report['total_amount']);
        $this->assertSame(1, $report['count']);
    }

    // ─────────────────────────────────────────────────────────────
    // EDGE CASES — filtros y exportación
    // ─────────────────────────────────────────────────────────────

    public function test_reporte_filtra_por_metodo_de_pago(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $this->makeSale($restaurant, 1000, 'cash');
        $this->makeSale($restaurant, 500, 'card');

        $report = $this->makeReport(["paymentMethod" => "cash"])->getReportData();

        $this->assertSame(1000.0, $report['total_amount']);
        $this->assertSame(1, $report['count']);
        $this->assertCount(1, $report['by_method']);
    }

    public function test_reporte_sin_ventas_devuelve_ceros(): void
    {
        $restaurant = $this->makeRestaurant(1);

        $report = $this->makeReport()->getReportData();

        $this->assertSame(0.0, $report['total_amount']);
        $this->assertSame(0, $report['count']);
        $this->assertSame(0, $report['average']);
        $this->assertEmpty($report['by_day']);
        $this->assertEmpty($report['top_products']);
    }

    public function test_export_csv_genera_archivo_con_encabezados_y_filas(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $this->makeSale($restaurant, 1000, 'cash');

        $page = $this->makeReport();
        $response = $page->exportCsv();

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);

        // Capturar el contenido del stream.
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        // BOM para Excel + encabezados + 1 fila de venta.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        // fputcsv entrecomilla campos que contienen espacios, ej. "Total ($)".
        $this->assertStringContainsString('ID,Fecha,Método,Estado,"Total ($)",Cajero/a,Anulada', $csv);
        $this->assertStringContainsString('Efectivo', $csv);
        $this->assertStringContainsString('1.000,00', $csv);
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE DE CAJA (cierre de caja)
    // ─────────────────────────────────────────────────────────────

    public function test_reporte_caja_lista_cierres_del_rango(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $this->makeSale($restaurant, 1000, 'cash');
        $user = \App\Models\User::factory()->create(['restaurant_id' => 1]);

        // Crear una caja con ventas computables.
        \App\Models\Caja::query()->create([
            'restaurant_id' => 1,
            'opening_user_id' => $user->id,
            'closing_user_id' => $user->id,
            'initial_balance' => 5000,
            'final_balance' => 6500,
            'status' => 'cerrada',
            'opening_date' => now()->subDay(),
            'closing_date' => now()->subDay()->addHours(8),
        ]);

        $report = $this->makeReport()->getCajaReport();

        $this->assertNotEmpty($report);
        $this->assertSame('Cerrada', $report[0]['status']);
        $this->assertSame(5000.0, $report[0]['opening_amount']);
        $this->assertSame(6500.0, $report[0]['closing_amount']);
    }

    public function test_reporte_caja_filtra_por_rango_de_fechas(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $user = \App\Models\User::factory()->create(['restaurant_id' => 1]);

        \App\Models\Caja::query()->create([
            'restaurant_id' => 1,
            'opening_user_id' => $user->id,
            'closing_user_id' => $user->id,
            'initial_balance' => 100,
            'status' => 'cerrada',
            'opening_date' => now()->subMonths(3),
            'closing_date' => now()->subMonths(3)->addHours(8),
        ]);

        $report = $this->makeReport([
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
        ])->getCajaReport();

        // Rango de 30 días: la caja de hace 3 meses queda fuera.
        $this->assertEmpty($report);
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE DE PRODUCTOS (más/menos vendidos)
    // ─────────────────────────────────────────────────────────────

    public function test_reporte_productos_distingue_mas_y_menos_vendidos(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $user = \App\Models\User::factory()->create(['restaurant_id' => $restaurant->id]);
        $table = Table::factory()->create(['restaurant_id' => $restaurant->id]);

        $productA = Product::factory()->create([
            'name' => 'Producto Top',
            'restaurant_id' => $restaurant->id,
            'price' => 100,
            'stock' => 100,
        ]);
        $productB = Product::factory()->create([
            'name' => 'Producto Bajo',
            'restaurant_id' => $restaurant->id,
            'price' => 100,
            'stock' => 100,
        ]);

        // Crear órdenes SIN el factory (que agrega orderProducts propios),
        // para controlar exactamente qué productos hay en el reporte.
        $orderA = Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'completed',
            'type' => 'salon',
            'table_id' => $table->id,
            'waiter_id' => $user->id,
        ]);
        OrderProduct::query()->create(['order_id' => $orderA->id, 'product_id' => $productA->id, 'quantity' => 8, 'price' => 100]);
        Sale::query()->create(['restaurant_id' => $restaurant->id, 'order_id' => $orderA->id, 'total_amount' => 800, 'payment_method' => 'cash', 'status' => 'paid', 'cashier_id' => $user->id]);

        $orderB = Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'completed',
            'type' => 'salon',
            'table_id' => $table->id,
            'waiter_id' => $user->id,
        ]);
        OrderProduct::query()->create(['order_id' => $orderB->id, 'product_id' => $productB->id, 'quantity' => 1, 'price' => 100]);
        Sale::query()->create(['restaurant_id' => $restaurant->id, 'order_id' => $orderB->id, 'total_amount' => 100, 'payment_method' => 'cash', 'status' => 'paid', 'cashier_id' => $user->id]);

        $report = $this->makeReport()->getProductReport();

        $this->assertSame('Producto Top', $report['top'][0]['name']);
        $this->assertGreaterThan(1, $report['top'][0]['quantity']);
        $this->assertSame('Producto Bajo', $report['bottom'][0]['name']);
        $this->assertSame(1, $report['bottom'][0]['quantity']);
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE DE GANANCIAS (mensual/semanal)
    // ─────────────────────────────────────────────────────────────

    public function test_reporte_ganancias_incluye_mes_actual(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $this->makeSale($restaurant, 2500, 'cash');

        $report = $this->makeReport()->getProfitReport();

        $this->assertCount(6, $report['months']);
        $this->assertCount(8, $report['weeks']);
        // El mes actual debe reflejar la venta.
        $this->assertSame(2500.0, end($report['months'])['total']);
    }
}