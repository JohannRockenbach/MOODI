<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BurgerMenuSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. LIMPIEZA SEGURA (SIN BORRAR USUARIOS NI ROLES)
        $this->command->info('Limpiando tablas de menú e inventario...');
        Schema::disableForeignKeyConstraints();
        
        // Truncar tablas de inventario y menú
        DB::table('ingredient_recipe')->truncate();
        IngredientBatch::truncate();
        Product::truncate();
        Recipe::truncate();
        Ingredient::truncate();
        Category::truncate();
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('Tablas limpiadas.');

        // 2. CREAR CATEGORÍAS
        $catBurgers = Category::create(['name' => 'Hamburguesas']);
        $catPapas = Category::create(['name' => 'Papas Fritas']);
        $catBebidas = Category::create(['name' => 'Bebidas']);

        // 3. CREAR INGREDIENTES (PARA RECETAS)
        $ingredientList = [
            // Insumos para Pan (Stock 0, para futura Fase 2)
            ['Harina', 'kg', 5],
            ['Levadura', 'g', 50],
            ['Sal', 'g', 100],
            ['Azúcar', 'g', 100],
            ['Leche', 'l', 2],
            ['Huevo (para pan)', 'unidad', 10],
            ['Manteca', 'kg', 1],
            
            // Insumos de Hamburguesa (Stock Real)
            ['Pan de Papa', 'unidad', 20],        
            ['Medallón Carne 120g', 'unidad', 20],
            ['Queso Cheddar', 'lámina', 40],   
            ['Cebolla Caramelizada', 'g', 500],
            ['Cebolla Cruda', 'g', 500],       
            ['Pepinillos', 'g', 300],         
            ['Ketchup', 'ml', 1000],          
            ['Mayonesa', 'ml', 1000],         
            ['Lechuga', 'g', 1000],           
            ['Tomate', 'g', 1000],            
            ['Huevo Frito', 'unidad', 20],      
            ['Panceta', 'lámina', 50],      
            ['Jamón', 'lámina', 50],        
            ['Queso Dambo', 'lámina', 50],      
            ['Palta', 'g', 500],              
            ['Papas Congeladas', 'kg', 10],    
        ];

        $ingredients = [];
        foreach ($ingredientList as $data) {
            $ingredients[$data[0]] = Ingredient::create([
                'name' => $data[0],
                'measurement_unit' => $data[1],
                'min_stock' => $data[2],
                'restaurant_id' => 1,
            ]);
        }
        $this->command->info('Ingredientes creados.');

        // 4. CARGAR LOTES (STOCK) A LOS INGREDIENTES
        $this->addBatch($ingredients['Pan de Papa'], 100, 5);
        $this->addBatch($ingredients['Medallón Carne 120g'], 200, 7);
        $this->addBatch($ingredients['Queso Cheddar'], 300, 10);
        $this->addBatch($ingredients['Cebolla Caramelizada'], 2000, 3);
        $this->addBatch($ingredients['Cebolla Cruda'], 2000, 5);
        $this->addBatch($ingredients['Pepinillos'], 1000, 15);
        $this->addBatch($ingredients['Ketchup'], 2000, 20);
        $this->addBatch($ingredients['Mayonesa'], 2000, 20);
        $this->addBatch($ingredients['Lechuga'], 2000, 3);
        $this->addBatch($ingredients['Tomate'], 2000, 3);
        $this->addBatch($ingredients['Huevo Frito'], 50, 2);
        $this->addBatch($ingredients['Panceta'], 200, 10);
        $this->addBatch($ingredients['Jamón'], 100, 10);
        $this->addBatch($ingredients['Queso Dambo'], 100, 10);
        $this->addBatch($ingredients['Palta'], 1000, 2);
        $this->addBatch($ingredients['Papas Congeladas'], 20, 30);
        $this->command->info('Lotes de stock agregados.');

        // 5. CREAR RECETAS (Hamburguesas y Papas)
        $recipeList = [
            'Burger Clásica' => [
                $ingredients['Pan de Papa']->id => 1, 
                $ingredients['Medallón Carne 120g']->id => 1, 
                $ingredients['Lechuga']->id => 30, 
                $ingredients['Tomate']->id => 20
            ],
            'Cheeseburger' => [
                $ingredients['Pan de Papa']->id => 1, 
                $ingredients['Medallón Carne 120g']->id => 1, 
                $ingredients['Queso Cheddar']->id => 1
            ],
            'Bacon Cheeseburger' => [
                $ingredients['Pan de Papa']->id => 1, 
                $ingredients['Medallón Carne 120g']->id => 1, 
                $ingredients['Queso Cheddar']->id => 1, 
                $ingredients['Panceta']->id => 2
            ],
            'Doble Cuarto' => [
                $ingredients['Pan de Papa']->id => 1, 
                $ingredients['Medallón Carne 120g']->id => 2, 
                $ingredients['Queso Cheddar']->id => 2, 
                $ingredients['Cebolla Cruda']->id => 20, 
                $ingredients['Ketchup']->id => 10, 
                $ingredients['Pepinillos']->id => 15
            ],
            'Burger Completa' => [
                $ingredients['Pan de Papa']->id => 1, 
                $ingredients['Medallón Carne 120g']->id => 1, 
                $ingredients['Jamón']->id => 1, 
                $ingredients['Queso Dambo']->id => 1, 
                $ingredients['Huevo Frito']->id => 1, 
                $ingredients['Lechuga']->id => 20, 
                $ingredients['Tomate']->id => 20
            ],
            'Avocado Burger' => [
                $ingredients['Pan de Papa']->id => 1, 
                $ingredients['Medallón Carne 120g']->id => 1, 
                $ingredients['Palta']->id => 50, 
                $ingredients['Tomate']->id => 20, 
                $ingredients['Cebolla Cruda']->id => 20
            ],
            'Royal Burger' => [
                $ingredients['Pan de Papa']->id => 1, 
                $ingredients['Medallón Carne 120g']->id => 2, 
                $ingredients['Queso Cheddar']->id => 2, 
                $ingredients['Panceta']->id => 2, 
                $ingredients['Huevo Frito']->id => 1, 
                $ingredients['Cebolla Caramelizada']->id => 30
            ],
            'Simple Burger' => [
                $ingredients['Pan de Papa']->id => 1, 
                $ingredients['Medallón Carne 120g']->id => 1
            ],
            'Onion Boom' => [
                $ingredients['Pan de Papa']->id => 1, 
                $ingredients['Medallón Carne 120g']->id => 1, 
                $ingredients['Queso Cheddar']->id => 2, 
                $ingredients['Cebolla Caramelizada']->id => 30, 
                $ingredients['Cebolla Cruda']->id => 20
            ],
            'Triple Mega' => [
                $ingredients['Pan de Papa']->id => 1, 
                $ingredients['Medallón Carne 120g']->id => 3, 
                $ingredients['Queso Cheddar']->id => 3, 
                $ingredients['Panceta']->id => 3
            ],
            'Papas Fritas Clásicas' => [
                $ingredients['Papas Congeladas']->id => 0.3
            ],
            'Papas Cheddar y Panceta' => [
                $ingredients['Papas Congeladas']->id => 0.3, 
                $ingredients['Queso Cheddar']->id => 2, 
                $ingredients['Panceta']->id => 2
            ],
            'Papas Huevo Jamón' => [
                $ingredients['Papas Congeladas']->id => 0.3, 
                $ingredients['Jamón']->id => 1, 
                $ingredients['Huevo Frito']->id => 1
            ],
        ];

        $recipes = [];
        foreach ($recipeList as $name => $recipeIngredients) {
            $recipe = Recipe::create([
                'name' => $name,
                'instructions' => 'Preparar según procedimiento estándar de ' . $name,
            ]);
            
            // Preparar datos para attach con required_amount
            $syncData = [];
            foreach ($recipeIngredients as $ingredientId => $quantity) {
                $syncData[$ingredientId] = ['required_amount' => $quantity];
            }
            $recipe->ingredients()->attach($syncData);
            
            $recipes[$name] = $recipe;
        }
        $this->command->info('Recetas creadas.');

        // 6. CREAR PRODUCTOS (El Menú)
        // (Formato: [Nombre, Categoría, Precio, Receta (opcional), Stock Directo (opcional)] )
        $productList = [
            // Hamburguesas
            ['Burger Clásica', $catBurgers, 1000, $recipes['Burger Clásica'], null],
            ['Cheeseburger', $catBurgers, 1200, $recipes['Cheeseburger'], null],
            ['Bacon Cheeseburger', $catBurgers, 1400, $recipes['Bacon Cheeseburger'], null],
            ['Doble Cuarto', $catBurgers, 1800, $recipes['Doble Cuarto'], null],
            ['Burger Completa', $catBurgers, 1700, $recipes['Burger Completa'], null],
            ['Avocado Burger', $catBurgers, 1600, $recipes['Avocado Burger'], null],
            ['Royal Burger', $catBurgers, 2000, $recipes['Royal Burger'], null],
            ['Simple Burger', $catBurgers, 900, $recipes['Simple Burger'], null],
            ['Onion Boom', $catBurgers, 1500, $recipes['Onion Boom'], null],
            ['Triple Mega', $catBurgers, 2300, $recipes['Triple Mega'], null],
            
            // Papas
            ['Papas Fritas Clásicas', $catPapas, 700, $recipes['Papas Fritas Clásicas'], null],
            ['Papas Cheddar y Panceta', $catPapas, 900, $recipes['Papas Cheddar y Panceta'], null],
            ['Papas Huevo Jamón', $catPapas, 900, $recipes['Papas Huevo Jamón'], null],
            
            // Bebidas (Stock Directo)
            ['Coca-Cola 350ml', $catBebidas, 500, null, 50],
            ['Sprite 350ml', $catBebidas, 500, null, 50],
            ['Fanta 350ml', $catBebidas, 500, null, 50],
            ['Pinta IPA', $catBebidas, 800, null, 30],
            ['Pinta Honey', $catBebidas, 800, null, 30],
            ['Pinta Stout', $catBebidas, 800, null, 30],
        ];

        foreach ($productList as $data) {
            Product::create([
                'name' => $data[0],
                'category_id' => $data[1]->id,
                'price' => $data[2],
                'recipe_id' => $data[3]?->id,
                'stock' => $data[4] ?? 0, // Stock para items de venta directa (Bebidas), 0 para recetas
                'min_stock' => $data[4] ? (int)($data[4] * 0.2) : 0, // Min stock 20% del inicial, 0 para recetas
                'restaurant_id' => 1,
            ]);
        }
        $this->command->info('Menú de productos y bebidas creado.');
        $this->command->info('¡Seeder de Hamburguesería completado!');
    }

    /** 
     * Helper para añadir lotes de prueba 
     */
    private function addBatch(Ingredient $ingredient, float $quantity, int $daysUntilExpires): void
    {
        $ingredient->batches()->create([
            'quantity' => $quantity,
            'purchase_date' => now(),
            'expiration_date' => now()->addDays($daysUntilExpires),
            'notes' => 'Lote inicial de prueba',
        ]);
    }
}
