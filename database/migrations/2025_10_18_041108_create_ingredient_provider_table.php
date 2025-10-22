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
    // El nombre de la tabla es la combinación de las dos tablas que une,
    // en singular y en orden alfabético: 'ingredient_provider'.
    Schema::create('ingredient_provider', function (Blueprint $table) {

        // --- Claves Foráneas que forman la Clave Primaria Compuesta ---

        // 1. Conexión con la tabla 'ingredients'.
        $table->foreignId('ingredient_id')
              ->constrained('ingredients')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si se borra un ingrediente, se borra esta relación.

        // 2. Conexión con la tabla 'providers'.
        $table->foreignId('provider_id')
              ->constrained('providers')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si se borra un proveedor, se borra esta relación.

        // --- Atributos propios de la relación ---

        // El precio al que este proveedor nos vende este ingrediente.
        $table->decimal('purchase_price', 8, 2);

        // La unidad en la que compramos (Ej: "Bolsa de 25kg", "Caja de 12 unidades").
        // Puede ser diferente a la unidad en que medimos el stock.
        $table->string('purchase_unit')->nullable();

        // --- Definición de la Clave Primaria Compuesta ---
        // Le decimos a la base de datos que la combinación de 'ingredient_id' y 'provider_id'
        // debe ser única. Esto evita registrar dos veces el mismo ingrediente para el mismo proveedor.
        $table->primary(['ingredient_id', 'provider_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_provider');
    }
};
