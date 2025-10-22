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
    Schema::create('purchase_orders', function (Blueprint $table) {
        // Clave Primaria (PK) autoincremental.
        $table->id();

        // Fecha esperada de entrega del pedido. Puede ser nula si no se conoce.
        $table->date('expected_delivery_date')->nullable();

        // Estado de la orden (Ej: 'pendiente', 'aprobada', 'recibida', 'cancelada').
        $table->string('status')->default('pendiente');

        // --- Relaciones (Claves Foráneas) ---

        // El proveedor al que se le hace el pedido.
        // Si el proveedor se elimina, este campo se vuelve NULL para no perder el histórico.
        $table->foreignId('provider_id')
              ->nullable()
              ->constrained('providers')
              ->onUpdate('cascade')
              ->onDelete('set null');

        // El usuario que solicitó la orden de compra.
        // Si el usuario se elimina, este campo se vuelve NULL.
        $table->foreignId('requester_id')
              ->nullable()
              ->constrained('users')
              ->onUpdate('cascade')
              ->onDelete('set null');

        // La orden de compra pertenece a un restaurante.
        $table->foreignId('restaurant_id')
              ->constrained('restaurants')
              ->onUpdate('cascade')
              ->onDelete('cascade');

        // La columna 'created_at' servirá como la fecha de creación de la orden.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
