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
            // La fecha y hora exactas para la cual se hizo la reserva.
            'reservation_time' => $this->faker->dateTimeBetween('now', '+1 month'),

            // La cantidad de comensales para la reserva.
            'guest_count' => $this->faker->numberBetween(1, 10),

            // Estado de la reserva (valores de la app: pending | confirmed | cancelled).
            'status' => 'pending',

            // El cliente que hizo la reserva (customer_id -> users).
            // En MOODI los clientes son usuarios; el rol 'cliente' es un User.
            'customer_id' => User::factory(),

            // La mesa reservada.
            'table_id' => Table::factory(),

            // La reserva pertenece a un restaurante.
            'restaurant_id' => Restaurant::factory(),
        ];
    }

    /**
     * Estado pendiente.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Estado confirmada.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    /**
     * Estado cancelada.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Reserva para fecha futura.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_time' => $this->faker->dateTimeBetween('+1 day', '+2 weeks'),
            'status' => 'confirmed',
        ]);
    }

    /**
     * Reserva para hoy.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_time' => $this->faker->dateTimeBetween('now', 'today 23:59:59'),
            'status' => 'confirmed',
        ]);
    }
}