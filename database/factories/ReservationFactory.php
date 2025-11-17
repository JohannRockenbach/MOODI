<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->faker->numerify('11########'),
            'number_of_people' => $this->faker->numberBetween(1, 10),
            'status' => $this->faker->randomElement(['pendiente', 'confirmada', 'cancelada', 'completada']),
            'special_requests' => $this->faker->optional(0.3)->sentence(),
            'table_id' => Table::factory(),
            'user_id' => User::factory(),
            'restaurant_id' => Restaurant::factory(),
        ];
    }

    /**
     * Estado pendiente
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pendiente',
        ]);
    }

    /**
     * Estado confirmada
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmada',
        ]);
    }

    /**
     * Estado cancelada
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelada',
        ]);
    }

    /**
     * Estado completada
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completada',
        ]);
    }

    /**
     * Reserva para fecha futura
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_date' => $this->faker->dateTimeBetween('+1 day', '+2 weeks'),
            'status' => 'confirmada',
        ]);
    }

    /**
     * Reserva para hoy
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_date' => $this->faker->dateTimeBetween('now', 'today 23:59:59'),
            'status' => 'confirmada',
        ]);
    }
}
