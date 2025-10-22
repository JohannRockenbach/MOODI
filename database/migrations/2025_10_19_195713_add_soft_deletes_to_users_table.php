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
        Schema::table('users', function (Blueprint $table) {
            // Este método simple añade la columna 'deleted_at' de tipo TIMESTAMP nullable.
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Este método elimina la columna 'deleted_at'.
            // Es importante para que la migración sea reversible.
            $table->dropSoftDeletes();
        });
    }
};