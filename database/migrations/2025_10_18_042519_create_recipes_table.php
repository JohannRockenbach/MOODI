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
    Schema::create('recipes', function (Blueprint $table) {
        // Clave Primaria (PK) autoincremental.
        $table->id();

        // Un nombre para identificar fácilmente la receta (Ej: "Masa de Pizza Clásica").
        $table->string('name')->unique();

        // Las instrucciones para preparar la receta. Usamos 'text' porque pueden ser muy largas.
        $table->text('instructions');

        // Columnas de fecha y hora.
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
