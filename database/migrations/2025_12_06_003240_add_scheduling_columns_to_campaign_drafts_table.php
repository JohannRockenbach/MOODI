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
            $table->timestamp('scheduled_date')->nullable()->after('valid_until');
            $table->string('status')->default('draft')->after('scheduled_date'); // draft, scheduled, sent
            $table->foreignId('restaurant_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_drafts', function (Blueprint $table) {
            $table->dropColumn(['scheduled_date', 'status', 'restaurant_id']);
        });
    }
};
