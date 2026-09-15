<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill defensivo por si existen registros legacy en null.
        DB::table('orders')
            ->whereNull('stock_deducted')
            ->update(['stock_deducted' => false]);

        // Asegurar contrato de columna: default false + NOT NULL.
        DB::statement('ALTER TABLE orders ALTER COLUMN stock_deducted SET DEFAULT false');
        DB::statement('ALTER TABLE orders ALTER COLUMN stock_deducted SET NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE orders ALTER COLUMN stock_deducted DROP DEFAULT');
    }
};
