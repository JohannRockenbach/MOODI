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
    // Nombre de la tabla: combinación de las dos tablas, singular.
    Schema::create('discount_sale', function (Blueprint $table) {

        // --- Claves Foráneas que forman la Clave Primaria Compuesta ---

        // 1. Conexión con la tabla 'discounts'.
        $table->foreignId('discount_id')
              ->constrained('discounts')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si se borra el descuento, se borra su aplicación aquí.

        // 2. Conexión con la tabla 'sales'.
        $table->foreignId('sale_id')
              ->constrained('sales')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si se borra la venta, se borran los descuentos aplicados.

        // --- Atributo propio de la relación ---

        // "Congelamos" el monto exacto que se descontó en esta venta específica.
        // Esto es VITAL para la precisión de los reportes históricos.
        $table->decimal('amount_discounted', 10, 2);


        // --- Definición de la Clave Primaria Compuesta ---
        // Asegura que no podamos aplicar el mismo código de descuento dos veces a la misma venta.
        $table->primary(['discount_id', 'sale_id']);
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_sale');
    }
};
