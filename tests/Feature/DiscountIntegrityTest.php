<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeRestaurant(int $id): Restaurant
    {
        return Restaurant::factory()->create(['id' => $id]);
    }

    private function makeDiscount(Restaurant $restaurant, bool $active = true): Discount
    {
        return Discount::query()->create([
            'name' => 'Descuento '.uniqid(),
            'code' => 'DSC'.bin2hex(random_bytes(3)),
            'type' => 'percentage',
            'value' => 10,
            'is_active' => $active,
            'restaurant_id' => $restaurant->id,
        ]);
    }

    private function attachDiscountToSale(Discount $discount): Sale
    {
        $restaurant = $discount->restaurant;
        $table = Table::factory()->create(['restaurant_id' => $restaurant->id]);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'completed',
            'type' => 'salon',
            'table_id' => $table->id,
        ]);

        $sale = Sale::query()->create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'total_amount' => 1000,
            'payment_method' => 'cash',
            'status' => 'paid',
        ]);

        $sale->discounts()->attach($discount->id, ['amount_discounted' => 100]);

        return $sale;
    }

    // ─────────────────────────────────────────────────────────────
    // HAPPY PATH
    // ─────────────────────────────────────────────────────────────

    public function test_descuento_sin_ventas_puede_eliminarse(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $discount = $this->makeDiscount($restaurant);

        $discount->delete();

        // No usa SoftDeletes: se elimina físicamente.
        $this->assertDatabaseMissing('discounts', ['id' => $discount->id]);
    }

    // ─────────────────────────────────────────────────────────────
    // SEGURIDAD / INTEGRIDAD: no eliminar si está asociado a ventas
    // ─────────────────────────────────────────────────────────────

    public function test_descuento_asociado_a_venta_no_se_puede_eliminar(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $discount = $this->makeDiscount($restaurant);
        $this->attachDiscountToSale($discount);

        try {
            $discount->delete();
            $this->fail('Debería lanzar DomainException al eliminar descuento con ventas.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('está asociado a ventas', $e->getMessage());
        }

        // El descuento sigue existiendo.
        $this->assertDatabaseHas('discounts', ['id' => $discount->id]);
    }

    public function test_bulk_delete_no_elimina_descuento_con_ventas(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $discountConVenta = $this->makeDiscount($restaurant);
        $this->attachDiscountToSale($discountConVenta);

        $this->expectException(\DomainException::class);
        $discountConVenta->delete();
    }

    // ─────────────────────────────────────────────────────────────
    // EDGE CASE: solo descuentos activos se asignan
    // ─────────────────────────────────────────────────────────────

    public function test_descuento_inactivo_puede_eliminarse_si_no_tiene_ventas(): void
    {
        $restaurant = $this->makeRestaurant(1);
        $inactive = $this->makeDiscount($restaurant, false);

        $inactive->delete();

        $this->assertDatabaseMissing('discounts', ['id' => $inactive->id]);
    }
}