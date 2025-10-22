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
    Schema::create('categories', function (Blueprint $table) {
        // Clave Primaria (PK) autoincremental.
        $table->id();

        // Nombre de la categoría (Ej: "Bebidas", "Pizzas", "Postres").
        // Haremos que el nombre sea único para evitar categorías duplicadas.
        $table->string('name')->unique();

        // Una descripción opcional para la categoría.
        // ->nullable() permite que este campo pueda quedar vacío.
        $table->text('description')->nullable();

        // Columnas de fecha y hora para creación y actualización.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
