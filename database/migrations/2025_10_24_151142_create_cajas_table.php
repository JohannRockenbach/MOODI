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
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();

            // Timestamps para apertura y cierre
            $table->timestamp('opening_date')->useCurrent(); // Se establece al crear el registro
            $table->timestamp('closing_date')->nullable();

            // Montos de la caja. Usamos 'decimal' para precisión monetaria.
            $table->decimal('initial_balance', 10, 2);
            $table->decimal('final_balance', 10, 2)->nullable();
            $table->decimal('total_sales', 10, 2)->nullable(); // Se calculará al cerrar

            // Estado de la caja
            $table->string('status')->default('abierta'); // Ej: abierta, cerrada

            // Relaciones con Usuarios
            $table->foreignId('opening_user_id')->constrained('users');
            $table->foreignId('closing_user_id')->nullable()->constrained('users');

            // Relación con Restaurante
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
