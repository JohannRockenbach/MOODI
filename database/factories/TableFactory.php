<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Table>
 */
class TableFactory extends Factory
{
    protected $model = Table::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['available', 'occupied', 'reserved', 'cleaning'];
        $locations = ['Interior', 'Exterior', 'Terraza', 'VIP', 'Bar', 'Ventana'];

        return [
            'number' => fake()->unique()->numberBetween(1, 50),
            'capacity' => fake()->randomElement([2, 4, 6, 8]), // Capacidad típica de mesas
            'location' => fake()->randomElement($locations),
            'status' => fake()->randomElement($statuses),
            'waiter_id' => fake()->optional(0.5)->randomElement(User::pluck('id')->toArray()), // 50% de probabilidad de tener mozo asignado
            'restaurant_id' => Restaurant::factory(),
        ];
    }

    /**
     * Mesa disponible.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'available',
            'waiter_id' => null,
        ]);
    }

    /**
     * Mesa ocupada.
     */
    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'occupied',
        ]);
    }

    /**
     * Mesa reservada.
     */
    public function reserved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reserved',
        ]);
    }

    /**
     * Mesa en limpieza.
     */
    public function cleaning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cleaning',
            'waiter_id' => null,
        ]);
    }

    /**
     * Mesa para un restaurante específico.
     */
    public function forRestaurant(int $restaurantId): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $restaurantId,
        ]);
    }

    /**
     * Mesa con mozo asignado específico.
     */
    public function withWaiter(int $waiterId): static
    {
        return $this->state(fn (array $attributes) => [
            'waiter_id' => $waiterId,
        ]);
    }
}
