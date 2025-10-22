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
    Schema::create('providers', function (Blueprint $table) {
        // Clave Primaria (PK) autoincremental.
        $table->id();

        // Razón Social o nombre comercial del proveedor.
        $table->string('business_name');

        // CUIT del proveedor. Debe ser único para no duplicar proveedores.
        $table->string('cuit')->unique();

        // Teléfono de contacto del proveedor. Puede ser opcional.
        $table->string('phone')->nullable();

        // Email de contacto del proveedor. También puede ser opcional.
        $table->string('email')->nullable();

        // --- Relación con Restaurante ---
        // Un proveedor está asociado a un restaurante específico.
        $table->foreignId('restaurant_id')
              ->constrained('restaurants')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si el restaurante se borra, sus proveedores también.

        // Columnas de fecha y hora.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
