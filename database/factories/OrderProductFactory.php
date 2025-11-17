<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderProduct>
 */
class OrderProductFactory extends Factory
{
    protected $model = OrderProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Notas especiales que puede tener un producto en un pedido
        $possibleNotes = [
            'Sin cebolla',
            'Poco cocido',
            'Muy cocido',
            'Sin sal',
            'Extra queso',
            'Sin gluten',
            'Picante',
            'Sin mostaza',
            'Con papas fritas',
            'Sin lechuga',
            'Extra salsa',
            'Para llevar',
        ];

        return [
            'order_id' => Order::factory(), // Se sobrescribirá cuando se use desde OrderFactory
            'product_id' => Product::factory(), // Se sobrescribirá cuando se use desde OrderFactory
            'quantity' => fake()->numberBetween(1, 5), // Entre 1 y 5 unidades
            'notes' => fake()->optional(0.3)->randomElement($possibleNotes), // 30% de probabilidad de tener notas
        ];
    }

    /**
     * Asociar a un pedido específico.
     */
    public function forOrder(int $orderId): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $orderId,
        ]);
    }

    /**
     * Asociar a un producto específico.
     */
    public function forProduct(int $productId): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    /**
     * Establecer cantidad específica.
     */
    public function quantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
        ]);
    }
}
