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
            $table->boolean('is_temporal')->default(false)->after('category_id');
            $table->foreignId('critical_ingredient_id')->nullable()->constrained('ingredient_batches')->nullOnDelete()->after('is_temporal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['critical_ingredient_id']);
            $table->dropColumn(['is_temporal', 'critical_ingredient_id']);
        });
    }
};
