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
        Schema::table('discounts', function (Blueprint $table) {
            // Vigencia horaria (CU-002.2 / OBJ-07): un descuento con franja
            // configurada solo es elegible si `now` cae dentro de la hora y el
            // día indicados. Vacío/null = elegible siempre.
            $table->time('starts_at')->nullable()->after('expires_at');
            $table->time('ends_at')->nullable()->after('starts_at');
            // Array de días 0=Lunes..6=Domingo (ISO-8601, 0-based). null = todos.
            $table->json('days_of_week')->nullable()->after('ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at', 'days_of_week']);
        });
    }
};
