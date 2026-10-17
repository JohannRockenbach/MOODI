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
        Schema::table('clientes', function (Blueprint $table) {
            // Saldo de puntos de fidelización (OBJ-06). Acumula 1 punto por cada
            // $100 de venta (floor) y se canjea 1 punto = $1 en el cobro.
            $table->unsignedInteger('loyalty_points')->default(0)->after('fcm_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('loyalty_points');
        });
    }
};
