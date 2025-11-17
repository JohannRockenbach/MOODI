<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categoryNames = [
            'Hamburguesas',
            'Pizzas',
            'Ensaladas',
            'Pastas',
            'Carnes',
            'Pescados',
            'Postres',
            'Bebidas',
            'Entradas',
            'Vegano',
            'Sin TACC',
            'Infantil',
            'Cafetería',
            'Bar',
            'Desayunos',
        ];

        return [
            'name' => fake()->unique()->randomElement($categoryNames),
            'description' => fake()->optional(0.7)->sentence(8), // 70% de probabilidad
            'parent_id' => null, // Por defecto sin categoría padre (categoría principal)
            'display_order' => fake()->numberBetween(1, 100),
            'settings' => null, // JSON settings, puede ser null o un array
        ];
    }

    /**
     * Categoría con padre (subcategoría).
     */
    public function withParent(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }

    /**
     * Categoría principal (sin padre).
     */
    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
        ]);
    }

    /**
     * Categoría con orden específico.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'display_order' => $order,
        ]);
    }

    /**
     * Categoría con settings específicos.
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => $settings,
        ]);
    }
}
