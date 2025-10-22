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
    Schema::create('users', function (Blueprint $table) {
        // --- Columnas Estándar de Laravel ---

        // Clave Primaria (PK) autoincremental.
        $table->id();

        // Nombre del usuario.
        $table->string('name');

        // Email del usuario. Laravel exige que sea único para la autenticación.
        $table->string('email')->unique();

        // Columna para registrar cuándo el usuario verificó su email. Es nullable.
        $table->timestamp('email_verified_at')->nullable();

        // Contraseña del usuario. Laravel se encargará de encriptarla (hashearla) automáticamente.
        $table->string('password');

        // Un token especial para la funcionalidad de "Recordar Sesión".
        $table->rememberToken();

        // Columnas de fecha y hora para creación y actualización.
        $table->timestamps();

        // --- Nuestras Columnas Personalizadas ---

        // Columna para el apellido del usuario.
        $table->string('last_name')->nullable();

        // Columna para el teléfono de contacto del usuario.
        $table->string('phone')->nullable();

        // Estado de la cuenta. Por defecto 'activo'. Podría ser 'inactivo', 'suspendido', etc.
        $table->string('account_status')->default('activo');

        // --- La Relación (Clave Foránea) ---

        // Esta es la forma moderna en Laravel de definir una clave foránea.
        // 1. foreignId('restaurant_id'): Crea una columna BIGINT UNSIGNED llamada 'restaurant_id'.
        //    Sigue la convención: nombre_tabla_singular_id.
        // 2. constrained('restaurants'): Establece la restricción de clave foránea.
        //    Le dice a la base de datos que esta columna se refiere a la columna 'id' de la tabla 'restaurants'.
        // 3. onDelete('cascade'): Si un restaurante es eliminado, todos los usuarios asociados a él
        //    también serán eliminados en cascada. Esto mantiene la integridad de los datos.
        //$table->foreignId('restaurant_id')
         //     ->constrained('restaurants')
           //   ->onUpdate('cascade')
             // ->onDelete('cascade');
    });
}  

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
