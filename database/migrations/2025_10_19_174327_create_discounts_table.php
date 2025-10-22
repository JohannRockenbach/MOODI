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
    //Descuentos.
    Schema::create('discounts', function (Blueprint $table) {
        // Clave Primaria (PK) autoincremental.
        $table->id();

        // Código del descuento que el cliente o cajero puede usar (Ej: "VERANO2024").
        // Debe ser único para evitar confusiones.
        $table->string('code')->unique();

        // Descripción de la promoción (Ej: "20% de descuento en postres los martes").
        $table->text('description')->nullable();

        // Tipo de descuento: 'percentage' (porcentaje) o 'fixed' (monto fijo).
        // Usaremos un default para que sea el tipo más común.
        $table->string('type')->default('percentage');

        // El valor del descuento. Si es 'percentage', guardamos 20 para un 20%.
        // Si es 'fixed', guardamos el monto, ej: 500.00.
        $table->decimal('value', 8, 2);

        // --- Relación con Restaurante ---
        $table->foreignId('restaurant_id')
              ->constrained('restaurants')
              ->onUpdate('cascade')
              ->onDelete('cascade');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
