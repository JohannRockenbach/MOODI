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
        Schema::table('sales', function (Blueprint $table) {
            // Añadimos la clave foránea para la caja.
            // La venta pertenece a una sesión de caja.
            $table->foreignId('caja_id')
                  ->nullable() // Puede ser nulo por si hay ventas antiguas
                  ->constrained('cajas')
                  ->onDelete('set null'); // Si se borra la caja, la venta no se borra.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Eliminar la clave foránea y la columna
            $table->dropForeign(['caja_id']);
            $table->dropColumn('caja_id');
        });
    }
};
