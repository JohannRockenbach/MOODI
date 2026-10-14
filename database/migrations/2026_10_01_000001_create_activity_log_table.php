<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de auditoría de acciones críticas (TAREA 2.1 / OBJ-09).
     *
     * Solo lectura para super_admin. Guarda QUÉ se hizo, QUIÉN, SOBRE QUÉ
     * sujeto (modelo polimórfico) y datos mínimos de contexto (sin secretos).
     */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            // Usuario que ejecutó la acción (nullable: acciones de sistema/CLI).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Acción canónica: sale.annulled, sale.restored, user.created...
            $table->string('action');
            // Sujeto polimórfico: clase del modelo + id (nullable).
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // Contexto mínimo antes/después (JSON, sin secretos).
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};