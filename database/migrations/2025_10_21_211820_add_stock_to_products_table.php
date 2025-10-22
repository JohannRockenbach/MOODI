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
    Schema::table('products', function (Blueprint $table) {
        // Añadimos la columna de stock
        $table->integer('stock')
              ->nullable()        // Opcional, para productos fabricados
              ->default(0)        // Por defecto, 0
              ->after('price');   // La pondrá después de la columna 'price'
    });
}

    /**
     * Reverse the migrations.
     */
public function down(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->dropColumn('stock');
    });
}
};
