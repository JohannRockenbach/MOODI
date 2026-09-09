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
        Schema::table('campaign_drafts', function (Blueprint $table) {
            // Segmento de clientes destino: todos | cumpleanos | vip.
            // 'todos' por defecto para campañas programadas ya existentes.
            $table->string('segment')->default('todos')->after('coupon_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_drafts', function (Blueprint $table) {
            $table->dropColumn('segment');
        });
    }
};
