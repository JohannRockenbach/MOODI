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
    // Usamos Schema::table para MODIFICAR una tabla existente.
    Schema::table('products', function (Blueprint $table) {
        // Aquí añadimos la columna y la clave foránea a la tabla 'products'.
        $table->foreignId('recipe_id')
              ->nullable()
              ->after('restaurant_id') // Opcional: para poner la columna después de otra.
              ->constrained('recipes')
              ->onUpdate('cascade')
              ->onDelete('set null');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
