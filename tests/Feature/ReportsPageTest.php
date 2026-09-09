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

    private function makeReport(array $state = []): Reports
    {
        $page = new Reports();

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
}