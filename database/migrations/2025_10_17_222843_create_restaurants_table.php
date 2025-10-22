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
    // Usamos el "Schema builder" de Laravel para crear una nueva tabla.
    // El primer argumento es el nombre de la tabla: 'restaurants' (plural y en minúsculas).
    Schema::create('restaurants', function (Blueprint $table) {

        // Crea una columna 'id' de tipo BIGINT, autoincremental y sin signo.
        $table->id();

        // Aquí guardaremos el nombre del restaurante.
        $table->string('name');
        
        // Crea una columna 'address' de tipo VARCHAR.
        $table->string('address');

        // El método ->unique() añade una restricción a la base de datos
        // para asegurar que no puedan existir dos restaurantes con el mismo CUIT.
        $table->string('cuit')->unique();

        // El método ->nullable() permite que esta columna pueda quedar vacía (con valor NULL).
        // Útil por si un restaurante no tiene un horario definido al momento de crearlo.
        $table->text('schedules')->nullable();

        // Crea una columna 'contact_phone' de tipo VARCHAR, que también puede ser nula.
        $table->string('contact_phone')->nullable();

        //Este método crea DOS columnas de tipo TIMESTAMP:
        // 1. 'created_at': Se llena automáticamente con la fecha y hora de creación del registro.
        // 2. 'updated_at': Se actualiza automáticamente cada vez que se modifica el registro.
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
