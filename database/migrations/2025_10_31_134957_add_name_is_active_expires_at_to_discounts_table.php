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
            // Agregar nombre del descuento
            $table->string('name')->after('id')->nullable();
            
            // Agregar campo activo/inactivo
            $table->boolean('is_active')->default(true)->after('value');
            
            // Agregar fecha de expiración
            $table->timestamp('expires_at')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn(['name', 'is_active', 'expires_at']);
        });
    }
};
