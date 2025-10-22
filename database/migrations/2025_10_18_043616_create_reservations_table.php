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
    Schema::create('reservations', function (Blueprint $table) {
        $table->id();

        // La fecha y hora exactas para la cual se hizo la reserva.
        $table->dateTime('reservation_time');

        // La cantidad de comensales para la reserva.
        $table->unsignedInteger('guest_count');

        // Estado de la reserva (Ej: 'pendiente', 'confirmada', 'cancelada').
        $table->string('status')->default('pendiente');

        // --- Relaciones (Claves Foráneas) ---

        // El cliente que hizo la reserva. Asumimos que los clientes son 'users'.
        $table->foreignId('customer_id')
              ->constrained('users')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si el cliente borra su cuenta, se borran sus reservas.

        // La mesa que ha sido reservada.
        $table->foreignId('table_id')
              ->constrained('tables')
              ->onUpdate('cascade');

        // La reserva pertenece a un restaurante.
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
        Schema::dropIfExists('reservations');
    }
};
