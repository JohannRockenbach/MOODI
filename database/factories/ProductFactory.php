<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Lista de nombres de comidas realistas para un restaurante
        $foodNames = [
            'Hamburguesa Clásica',
            'Pizza Margarita',
            'Ensalada César',
            'Pasta Carbonara',
            'Milanesa con Papas',
            'Lomito Completo',
            'Empanadas de Carne',
            'Tacos al Pastor',
            'Sushi Roll California',
            'Paella Valenciana',
            'Asado Argentino',
            'Ravioles de Ricota',
            'Pollo al Horno',
            'Pescado Grillado',
            'Wok de Verduras',
            'Costillas BBQ',
            'Sandwich de Atún',
            'Hamburguesa Vegana',
            'Nachos con Queso',
            'Tiramisú',
            'Flan Casero',
            'Helado de Chocolate',
            'Cerveza Artesanal',
            'Vino Tinto',
            'Limonada Natural',
            'Café Espresso',
            'Té Verde',
            'Smoothie de Frutas',
        ];

        return [
            'name' => fake()->randomElement($foodNames),
            'description' => fake()->optional(0.7)->sentence(10), // 70% de probabilidad de tener descripción
            'price' => fake()->randomFloat(2, 100, 8000), // Precios entre $100 y $8000
            'is_available' => fake()->boolean(85), // 85% de probabilidad de estar disponible
            'category_id' => Category::factory(),
            'restaurant_id' => Restaurant::factory(),
            'recipe_id' => null, // Por defecto null, se puede especificar después si es necesario
        ];
    }

    /**
     * Indica que el producto está disponible.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => true,
        ]);
    }

    /**
     * Indica que el producto NO está disponible.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }

    /**
     * Producto con categoría específica.
     */
    public function forCategory(int $categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }

    /**
     * Producto con restaurante específico.
     */
    public function forRestaurant(int $restaurantId): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $restaurantId,
        ]);
    }
}
