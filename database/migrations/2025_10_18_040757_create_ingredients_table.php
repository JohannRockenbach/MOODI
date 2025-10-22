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
    Schema::create('ingredients', function (Blueprint $table) {
        // Clave Primaria (PK) autoincremental.
        $table->id();

        // Nombre del ingrediente (Ej: "Harina 0000", "Tomate Perita", "Queso Muzarella").
        $table->string('name');

        // Stock actual del ingrediente. Usamos 'decimal' para permitir cantidades con coma (Ej: 1.5 kg).
        // '10, 3' permite hasta 10 dígitos, con 3 para decimales, dándonos precisión.
        $table->decimal('current_stock', 10, 3)->default(0);

        // La unidad en la que medimos el stock (Ej: "kg", "litros", "unidades").
        $table->string('measurement_unit');

        // Punto de reorden: la cantidad mínima de stock antes de que necesitemos comprar más.
        // Cuando el stock actual sea menor o igual a este punto, el sistema debería alertarnos.
        $table->decimal('reorder_point', 10, 3)->default(0);

        // --- Relación con Restaurante ---
        // Un ingrediente pertenece a un restaurante.
        $table->foreignId('restaurant_id')
              ->constrained('restaurants')
              ->onUpdate('cascade')
              ->onDelete('cascade');

        // Columnas de fecha y hora.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
