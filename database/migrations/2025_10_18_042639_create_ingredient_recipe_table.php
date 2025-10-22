<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    // Nombre de la tabla: combinación de las dos tablas, singular, orden alfabético.
    Schema::create('ingredient_recipe', function (Blueprint $table) {

        // --- Claves Foráneas que forman la Clave Primaria Compuesta ---

        // 1. Conexión con la tabla 'ingredients'.
        $table->foreignId('ingredient_id')
              ->constrained('ingredients')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si se borra un ingrediente, esta entrada en la receta se elimina.

        // 2. Conexión con la tabla 'recipes'.
        $table->foreignId('recipe_id')
              ->constrained('recipes')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si se borra la receta, se eliminan todos sus ingredientes asociados.

        // --- Atributo propio de la relación ---

        // La cantidad del ingrediente necesaria para la receta.
        // Usamos 'decimal' para soportar cantidades como "0.5 litros".
        $table->decimal('required_amount', 10, 3);

        // --- Definición de la Clave Primaria Compuesta ---
        // Asegura que no podamos añadir el mismo ingrediente dos veces a la misma receta.
        $table->primary(['ingredient_id', 'recipe_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_recipe');
    }
};
