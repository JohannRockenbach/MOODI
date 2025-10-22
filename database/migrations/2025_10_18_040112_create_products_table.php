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
    Schema::create('products', function (Blueprint $table) {
        // Clave Primaria (PK) autoincremental.
        $table->id();

        // Nombre del producto (Ej: "Pizza Margarita", "Coca-Cola 500ml").
        $table->string('name');

        // Descripción del producto, puede ser un texto más largo.
        $table->text('description')->nullable();

        // Precio del producto. Usamos decimal para manejar dinero de forma precisa.
        // '8, 2' significa que guardará hasta 8 dígitos en total, con 2 de ellos para decimales (Ej: 123456.78).
        $table->decimal('price', 8, 2);

        // Un booleano para saber si el producto está disponible para la venta.
        // Por defecto, un nuevo producto estará disponible (true).
        $table->boolean('is_available')->default(true);

        // --- Relaciones (Claves Foráneas) ---

        // 1. Conexión con 'categories'. Un producto PERTENECE a una categoría.
        $table->foreignId('category_id')
              ->constrained('categories')
              ->onUpdate('cascade'); // Si el id de una categoría cambia, se actualiza aquí.

        // 2. Conexión con 'restaurants'. Un producto PERTENECE a un restaurante.
        $table->foreignId('restaurant_id')
              ->constrained('restaurants')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si se borra el restaurante, se borran sus productos.

        // Columnas de fecha y hora.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
