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
        Schema::table('ingredients', function (Blueprint $table) {
            // Eliminar la columna current_stock ya que ahora el stock se gestiona por lotes
            $table->dropColumn('current_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            // Restaurar la columna current_stock en caso de rollback
            $table->decimal('current_stock', 10, 3)->default(0)->after('name');
        });
    }
};
