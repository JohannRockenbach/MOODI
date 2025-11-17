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
    Schema::create('tables', function (Blueprint $table) {
        // Clave Primaria (PK) autoincremental.
        $table->id();

        // Número de la mesa que ve el cliente (Ej: 5, 12, A3). Usamos string por si hay letras.
        $table->string('number');

        // Hacemos que el número de mesa sea único POR RESTAURANTE.
        $table->unique(['restaurant_id', 'number']);

        // Cuántas personas caben en la mesa.
        $table->unsignedInteger('capacity');

        // Ubicación de la mesa (Ej: "Terraza", "Salón Principal", "Junto a la ventana").
        $table->string('location')->nullable();

        // Estado actual de la mesa. Lo guardamos como texto para usar Enums en el futuro.
        // (Ej: 'disponible', 'ocupada', 'reservada').
        $table->string('status')->default('disponible');

        // --- Relaciones (Claves Foráneas) ---

        // El mozo asignado a esta mesa. Es 'nullable' porque una mesa puede no tener
        // un mozo asignado en un momento dado (Ej: al inicio del día).
        $table->foreignId('waiter_id')
              ->nullable()
              ->constrained('users')
              ->onUpdate('cascade')
              ->onDelete('set null'); // Si se borra el usuario (mozo), la mesa queda sin asignar.

        // La mesa pertenece a un restaurante.
        $table->foreignId('restaurant_id')
              ->constrained('restaurants')
              ->onUpdate('cascade')
              ->onDelete('cascade');

        // Columnas de fecha y hora.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
