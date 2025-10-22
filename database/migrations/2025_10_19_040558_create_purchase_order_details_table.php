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
    Schema::create('purchase_order_details', function (Blueprint $table) {
        // Clave primaria para cada línea de detalle.
        $table->id();

        // Cantidad solicitada del ingrediente.
        $table->decimal('quantity_requested', 10, 3);

        // Precio unitario del ingrediente al momento de la compra.
        // Guardamos esto aquí porque el precio puede cambiar en el futuro.
        $table->decimal('unit_price', 8, 2);

        // --- Relaciones (Claves Foráneas) ---

        // La línea de detalle pertenece a una orden de compra.
        // Si la orden se elimina, sus detalles se eliminan en cascada.
        $table->foreignId('purchase_order_id')
              ->constrained('purchase_orders')
              ->onUpdate('cascade')
              ->onDelete('cascade');

        // El ingrediente que se está solicitando.
        // Usamos 'restrict' para evitar que se borre un ingrediente si está en una orden de compra.
        $table->foreignId('ingredient_id')
              ->constrained('ingredients')
              ->onUpdate('cascade')
              ->onDelete('restrict');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_details');
    }
};
