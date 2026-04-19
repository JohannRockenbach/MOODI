<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('restaurant_id')
                ->nullable()
                ->after('user_id')
                ->constrained('restaurants')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });

        $defaultRestaurantId = DB::table('restaurants')->orderBy('id')->value('id');

        if ($defaultRestaurantId) {
            DB::table('clientes')
                ->whereNull('restaurant_id')
                ->update(['restaurant_id' => $defaultRestaurantId]);
        }
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');
        });
    }
};
