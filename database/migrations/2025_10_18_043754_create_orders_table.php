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
    Schema::create('orders', function (Blueprint $table) {
        $table->id();

        // Estado del pedido (Ej: 'en_proceso', 'servido', 'pagado').
        $table->string('status')->default('en_proceso');

        // Tipo de pedido para dar más flexibilidad al negocio.
        // (Ej: 'salon', 'delivery', 'para_llevar').
        $table->string('type')->default('salon');

        // --- Relaciones (Claves Foráneas) ---

        // La mesa donde se realizó el pedido.
        $table->foreignId('table_id')
              ->constrained('tables')
              ->onUpdate('cascade');

        // El mozo que tomó el pedido.
        $table->foreignId('waiter_id')
              ->constrained('users')
              ->onUpdate('cascade');

        // El pedido pertenece a un restaurante.
        $table->foreignId('restaurant_id')
              ->constrained('restaurants')
              ->onUpdate('cascade')
              ->onDelete('cascade');

        // La columna created_at servirá como la fecha y hora en que se creó el pedido.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
