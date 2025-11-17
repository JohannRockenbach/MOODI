<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Restaurant>
 */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $restaurantNames = [
            'El Buen Sabor',
            'La Parrilla Argentina',
            'Pizzería Don Juan',
            'Sushi House',
            'El Rincón Criollo',
            'Pasta & Vino',
            'La Esquina del Sabor',
            'Burger King\'s',
            'El Fogón',
            'Café Gourmet',
        ];

        $schedules = [
            'Lun-Vie: 12:00-15:00, 20:00-23:00',
            'Lun-Dom: 11:00-00:00',
            'Mar-Dom: 12:00-16:00, 19:00-00:00',
            'Lun-Sab: 11:30-15:30, 19:30-23:30',
        ];

        return [
            'name' => fake()->randomElement($restaurantNames) . ' ' . fake()->city(),
            'address' => fake()->streetAddress() . ', ' . fake()->city(),
            'cuit' => fake()->numerify('##-########-#'), // Formato CUIT argentino
            'schedules' => fake()->randomElement($schedules),
            'contact_phone' => fake()->numerify('+54 9 ### ### ####'), // Formato teléfono argentino
        ];
    }

    /**
     * Restaurante con nombre específico.
     */
    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }
}
