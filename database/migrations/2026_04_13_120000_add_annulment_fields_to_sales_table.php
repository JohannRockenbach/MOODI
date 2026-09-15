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
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('annulled_at')->nullable()->after('status');
            $table->foreignId('annulled_by')
                ->nullable()
                ->after('annulled_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('annulled_reason')->nullable()->after('annulled_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['annulled_by']);
            $table->dropColumn(['annulled_at', 'annulled_by', 'annulled_reason']);
        });
    }
};
