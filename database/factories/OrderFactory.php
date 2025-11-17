<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['pending', 'en_proceso', 'servido', 'pagado', 'cancelado'];
        $types = ['delivery', 'local', 'para_llevar'];

        return [
            'status' => fake()->randomElement($statuses),
            'type' => fake()->randomElement($types),
            'table_id' => Table::factory(),
            'waiter_id' => User::factory(), // El mozo que tomó el pedido
            'restaurant_id' => Restaurant::factory(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            // 1. Determinar cuántos productos tendrá este pedido (entre 1 y 5)
            $numberOfProducts = rand(1, 5);

            // 2. Crear productos disponibles para este pedido
            // Reutilizamos productos existentes si hay, o creamos nuevos
            $availableProducts = Product::where('is_available', true)
                ->where('restaurant_id', $order->restaurant_id)
                ->inRandomOrder()
                ->limit($numberOfProducts)
                ->get();

            // Si no hay suficientes productos, creamos los faltantes
            if ($availableProducts->count() < $numberOfProducts) {
                $needed = $numberOfProducts - $availableProducts->count();
                $newProducts = Product::factory()
                    ->count($needed)
                    ->forRestaurant($order->restaurant_id)
                    ->available()
                    ->create();
                
                $availableProducts = $availableProducts->merge($newProducts);
            }

            // 3. Variable para acumular el total del pedido
            $totalAmount = 0;

            // 4. Crear OrderProducts para cada producto seleccionado
            foreach ($availableProducts as $product) {
                $quantity = rand(1, 3); // Cantidad aleatoria entre 1 y 3
                
                // Crear el registro en order_product
                OrderProduct::factory()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);

                // Calcular el subtotal de este producto
                $subtotal = $product->price * $quantity;
                $totalAmount += $subtotal;
            }

            // 5. CRUCIAL: Actualizar el total del pedido con el monto real calculado
            // Nota: Como Order no tiene campo 'total_amount' en la migración,
            // este cálculo está listo para cuando agregues el campo.
            // Por ahora queda comentado para que no genere error.
            
            // $order->update([
            //     'total_amount' => $totalAmount,
            // ]);

            // Si en el futuro agregas el campo 'total_amount' a la migración de orders,
            // simplemente descomenta las líneas de arriba.
            
            // Por ahora, el total se puede calcular dinámicamente desde la relación:
            // $order->orderProducts()->sum(DB::raw('quantity * (SELECT price FROM products WHERE products.id = order_product.product_id)'))
        });
    }

    /**
     * Pedido pendiente.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Pedido en proceso.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'en_proceso',
        ]);
    }

    /**
     * Pedido servido.
     */
    public function served(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'servido',
        ]);
    }

    /**
     * Pedido pagado.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pagado',
        ]);
    }

    /**
     * Pedido cancelado.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelado',
        ]);
    }

    /**
     * Pedido para delivery.
     */
    public function delivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'delivery',
            'table_id' => null, // Delivery no tiene mesa
        ]);
    }

    /**
     * Pedido local (en el restaurante).
     */
    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'local',
        ]);
    }

    /**
     * Pedido para llevar.
     */
    public function takeaway(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'para_llevar',
            'table_id' => null, // Para llevar no tiene mesa
        ]);
    }

    /**
     * Asociar a un restaurante específico.
     */
    public function forRestaurant(int $restaurantId): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $restaurantId,
        ]);
    }

    /**
     * Asociar a una mesa específica.
     */
    public function forTable(int $tableId): static
    {
        return $this->state(fn (array $attributes) => [
            'table_id' => $tableId,
        ]);
    }

    /**
     * Asociar a un mozo específico.
     */
    public function forWaiter(int $waiterId): static
    {
        return $this->state(fn (array $attributes) => [
            'waiter_id' => $waiterId,
        ]);
    }
}
