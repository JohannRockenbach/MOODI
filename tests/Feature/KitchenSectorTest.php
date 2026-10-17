<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\KitchenDashboard;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CU-008 — KDS: división por sector de producción (comidas/bebidas/postres).
 * Los ítems de cada card se agrupan por product.category.name (excluyendo
 * "Bebidas"), con cabecera de sector por grupo. Solo presentación.
 *
 * Testing Strategy B:
 * - happy: pedido con ítems de 2 categorías → loadOrders agrupa en 2 sectores.
 * - edge: pedido solo con "Bebidas" no aparece; pedido sin ítems no rompe.
 * - seguridad: Cocinero sigue viendo el KDS; Mozo no.
 */
class KitchenSectorTest extends TestCase
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

    private function category(string $name): Category
    {
        return Category::factory()->create(['name' => $name]);
    }

    private function productFor(Category $category, string $name): Product
    {
        return Product::factory()->forRestaurant(1)->available()->create([
            'category_id' => $category->id,
            'name' => $name,
            'price' => 2000,
            'stock' => 20,
        ]);
    }

    /**
     * Crea el pedido con Order::create() (no el factory, cuyo hook
     * afterCreating agrega orderProducts aleatorios) para controlar EXACTAMENTE
     * los ítems y, por lo tanto, los sectores.
     *
     * @param  array<int, array{0: Product, 1: int}>  $items
     */
    private function makeOrder(array $items): Order
    {
        $order = Order::create([
            'status' => 'pending',
            'type' => 'para_llevar',
            'table_id' => null,
            'waiter_id' => User::factory()->create(['restaurant_id' => 1])->id,
            'restaurant_id' => 1,
        ]);

        foreach ($items as [$product, $quantity]) {
            $order->orderProducts()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $product->price,
            ]);
        }

        return $order;
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['restaurant_id' => 1]);
        $user->assignRole($role);

        return $user;
    }

    public function test_agrupa_items_de_dos_categorias_en_dos_sectores(): void
    {
        $carnes = $this->category('Carnes');
        $pizzas = $this->category('Pizzas');

        $asado = $this->productFor($carnes, 'Asado');
        $muzzarella = $this->productFor($pizzas, 'Muzzarella');

        $order = $this->makeOrder([
            [$asado, 1],
            [$muzzarella, 2],
        ]);

        $this->actingAs($this->user('Cocinero'));

        $component = new KitchenDashboard;
        $component->mount();

        $sectors = $component->orderProductsBySector($order);

        $this->assertCount(2, $sectors);
        $this->assertArrayHasKey('Carnes', $sectors);
        $this->assertArrayHasKey('Pizzas', $sectors);
        $this->assertSame('Asado', $sectors['Carnes'][0]->product->name);
        $this->assertSame('Muzzarella', $sectors['Pizzas'][0]->product->name);

        // El pedido aparece en Pendientes (tiene ítems fuera de Bebidas).
        $this->assertTrue($component->pendingOrders->pluck('id')->contains($order->id));
    }

    public function test_pedido_solo_bebidas_no_aparece_en_kds(): void
    {
        $bebidas = $this->category('Bebidas');
        $gaseosa = $this->productFor($bebidas, 'Coca-Cola');

        $order = $this->makeOrder([[$gaseosa, 2]]);

        $this->actingAs($this->user('Cocinero'));

        $component = new KitchenDashboard;
        $component->mount();

        $this->assertFalse($component->pendingOrders->pluck('id')->contains($order->id));
        $this->assertSame([], $component->orderProductsBySector($order));
    }

    public function test_pedido_sin_items_no_rompe(): void
    {
        $order = Order::create([
            'status' => 'pending',
            'type' => 'para_llevar',
            'table_id' => null,
            'waiter_id' => User::factory()->create(['restaurant_id' => 1])->id,
            'restaurant_id' => 1,
        ]);

        $this->actingAs($this->user('Cocinero'));

        $component = new KitchenDashboard;
        $component->mount(); // no debe lanzar

        // Sin ítems, el agrupamiento queda vacío (la card se renderiza sin sectores).
        $this->assertSame([], $component->orderProductsBySector($order));
    }

    public function test_bebidas_se_excluyen_del_agrupamiento(): void
    {
        $carnes = $this->category('Carnes');
        $bebidas = $this->category('Bebidas');

        $asado = $this->productFor($carnes, 'Asado');
        $gaseosa = $this->productFor($bebidas, 'Coca-Cola');

        $order = $this->makeOrder([
            [$asado, 1],
            [$gaseosa, 2],
        ]);

        $this->actingAs($this->user('Cocinero'));

        $component = new KitchenDashboard;
        $component->mount();

        $sectors = $component->orderProductsBySector($order);

        $this->assertCount(1, $sectors);
        $this->assertArrayHasKey('Carnes', $sectors);
        $this->assertArrayNotHasKey('Bebidas', $sectors);
    }

    public function test_cocinero_ve_el_kds_y_mozo_no(): void
    {
        $this->actingAs($this->user('Cocinero'));
        $this->assertTrue(KitchenDashboard::canAccess());

        $mozo = $this->user('Mozo');
        $this->actingAs($mozo);
        $this->assertFalse(KitchenDashboard::canAccess());

        Livewire::actingAs($mozo)
            ->test(KitchenDashboard::class)
            ->assertStatus(403);
    }
}
