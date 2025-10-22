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
    // El nombre de la tabla sigue la convención: singular y orden alfabético.
    Schema::create('order_product', function (Blueprint $table) {

        // A diferencia de otras tablas pivote, aquí añadimos un 'id' propio.
        // Esto es útil si alguna vez necesitas referenciar un ítem específico
        // de un pedido (Ej: "cancelar solo las papas fritas de este pedido").
        $table->id();

        // --- Claves Foráneas ---

        // 1. Conexión con la tabla 'orders'.
        $table->foreignId('order_id')
              ->constrained('orders')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si se borra un pedido, se borra su detalle.

        // 2. Conexión con la tabla 'products'.
        $table->foreignId('product_id')
              ->constrained('products')
              ->onUpdate('cascade'); // Si se actualiza un producto, la relación se mantiene.

        // --- Atributos propios de la relación ---

        // La cantidad de este producto específico en este pedido.
        $table->unsignedInteger('quantity');

        // Notas especiales para este ítem del pedido (Ej: "Sin cebolla", "Muy cocido").
        $table->text('notes')->nullable();

        // Añadimos timestamps a la tabla pivote. Esto puede ser útil para saber
        // si un ítem se añadió al pedido más tarde que otros.
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_product');
    }
};
