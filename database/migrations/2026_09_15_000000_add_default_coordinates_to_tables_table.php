<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La migración 2025_11_17 declaró `->default(0)` en las columnas pos_x/pos_y,
     * pero la base de datos dev quedó con NOT NULL sin default (imposible crear
     * mesas desde el mapa). Esta migración alinea el schema real con el archivo
     * original aplicando el default por `change()` (Postgres nativo, sin dbal).
     */
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->integer('pos_x')->default(0)->change();
            $table->integer('pos_y')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE tables ALTER COLUMN pos_x DROP DEFAULT');
        DB::statement('ALTER TABLE tables ALTER COLUMN pos_y DROP DEFAULT');
    }
};