<?php

namespace Database\Factories;

use App\Models\Caja;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'total_amount' => $this->faker->randomFloat(2, 1000, 50000),
            'payment_method' => $this->faker->randomElement(['efectivo', 'tarjeta_debito', 'tarjeta_credito', 'transferencia', 'mercado_pago']),
            'status' => $this->faker->randomElement(['completada', 'pendiente', 'cancelada']),
            'order_id' => Order::factory(),
            'restaurant_id' => Restaurant::factory(),
            'user_id' => User::factory(),
            'caja_id' => null, // Optional, can be set manually
        ];
    }

    /**
     * Venta completada
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completada',
        ]);
    }

    /**
     * Venta pendiente
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pendiente',
        ]);
    }

    /**
     * Venta cancelada
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelada',
        ]);
    }

    /**
     * Pago en efectivo
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'efectivo',
        ]);
    }

    /**
     * Pago con tarjeta
     */
    public function card(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => $this->faker->randomElement(['tarjeta_debito', 'tarjeta_credito']),
        ]);
    }

    /**
     * Asociada a una caja específica
     */
    public function forCaja(Caja $caja): static
    {
        return $this->state(fn (array $attributes) => [
            'caja_id' => $caja->id,
            'restaurant_id' => $caja->restaurant_id,
            'sale_date' => $this->faker->dateTimeBetween($caja->opening_date, $caja->closing_date ?? 'now'),
        ]);
    }
}
