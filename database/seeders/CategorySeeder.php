<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Main categories (idempotente)
        $burgers = Category::firstOrCreate(
            ['name' => 'Hamburguesas'],
            ['description' => 'Hamburguesas clásicas, especiales y opciones veggie']
        );
        // Children for hamburguesas: if a category with same name exists globally, don't try to create a duplicate
        $existing = Category::where('name', 'Clásicas')->first();
        if (! $existing) {
            Category::create(['name' => 'Clásicas', 'description' => 'Hamburguesas clásicas', 'parent_id' => $burgers->id]);
        } elseif (is_null($existing->parent_id)) {
            // if existing has no parent, assign it under the burgers category
            $existing->update(['parent_id' => $burgers->id, 'description' => $existing->description ?? 'Hamburguesas clásicas']);
        }

        $existing = Category::where('name', 'Especiales')->first();
        if (! $existing) {
            Category::create(['name' => 'Especiales', 'description' => 'Hamburguesas especiales', 'parent_id' => $burgers->id]);
        } elseif (is_null($existing->parent_id)) {
            $existing->update(['parent_id' => $burgers->id, 'description' => $existing->description ?? 'Hamburguesas especiales']);
        }

        $existing = Category::where('name', 'Veggie')->first();
        if (! $existing) {
            Category::create(['name' => 'Veggie', 'description' => 'Opciones vegetarianas', 'parent_id' => $burgers->id]);
        } elseif (is_null($existing->parent_id)) {
            $existing->update(['parent_id' => $burgers->id, 'description' => $existing->description ?? 'Opciones vegetarianas']);
        }

        $pizzas = Category::firstOrCreate(
            ['name' => 'Pizzas'],
            ['description' => 'Pizzas clásicas, especiales y calzones']
        );
        $existing = Category::where('name', 'Clásicas')->first();
        if (! $existing) {
            Category::create(['name' => 'Clásicas', 'description' => 'Pizzas clásicas', 'parent_id' => $pizzas->id]);
        } elseif (is_null($existing->parent_id)) {
            // only change parent if it had none
            $existing->update(['parent_id' => $pizzas->id, 'description' => $existing->description ?? 'Pizzas clásicas']);
        }

        $existing = Category::where('name', 'Especiales')->first();
        if (! $existing) {
            Category::create(['name' => 'Especiales', 'description' => 'Pizzas especiales', 'parent_id' => $pizzas->id]);
        } elseif (is_null($existing->parent_id)) {
            $existing->update(['parent_id' => $pizzas->id, 'description' => $existing->description ?? 'Pizzas especiales']);
        }

        $existing = Category::where('name', 'Calzones')->first();
        if (! $existing) {
            Category::create(['name' => 'Calzones', 'description' => 'Calzones', 'parent_id' => $pizzas->id]);
        } elseif (is_null($existing->parent_id)) {
            $existing->update(['parent_id' => $pizzas->id, 'description' => $existing->description ?? 'Calzones']);
        }

        Category::firstOrCreate(['name' => 'Acompañamientos / Frituras'], ['description' => 'Papas fritas, aros de cebolla, nuggets, bastones de muzza']);
        Category::firstOrCreate(['name' => 'Minutas / Otros'], ['description' => 'Milanesas, sándwiches, etc.']);
        Category::firstOrCreate(['name' => 'Bebidas sin Alcohol'], ['description' => 'Gaseosas, aguas, jugos']);
        Category::firstOrCreate(['name' => 'Bebidas con Alcohol'], ['description' => 'Cervezas, vinos (si aplica)']);
        Category::firstOrCreate(['name' => 'Adicionales / Extras'], ['description' => 'Queso extra, bacon, huevo, salsas']);
        Category::firstOrCreate(['name' => 'Combos / Promos'], ['description' => 'Promociones y combos']);
    }
}
