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
        Schema::table('orders', function (Blueprint $table) {
            // OBJ-04: comensales (pax) del pedido de salón. Se usa SOLO para
            // SUGERIR mesas libres por capacidad en el TPV (no bloquea la
            // creación ni la ocupación de la mesa). Nullable: los pedidos de
            // para llevar / delivery / web no llevan este dato.
            $table->unsignedInteger('guest_count')->nullable()->after('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('guest_count');
        });
    }
};
