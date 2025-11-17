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
            // Información personal
            $table->string('dni', 20)->nullable()->unique()->after('name');
            $table->string('address')->nullable()->after('phone');
            $table->date('birth_date')->nullable()->after('address');
            
            // Información laboral
            $table->enum('work_shift', ['dia', 'noche', 'mixto'])->default('dia')->after('birth_date');
            $table->enum('contract_type', ['permanente', 'temporal', 'medio_tiempo'])->default('permanente')->after('work_shift');
            $table->enum('employment_status', ['activo', 'inactivo'])->default('activo')->after('contract_type');
            $table->date('start_date')->nullable()->after('employment_status');
            $table->date('end_date')->nullable()->after('start_date');
            $table->text('observations')->nullable()->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'dni',
                'address',
                'birth_date',
                'work_shift',
                'contract_type',
                'employment_status',
                'start_date',
                'end_date',
                'observations',
            ]);
        });
    }
};
