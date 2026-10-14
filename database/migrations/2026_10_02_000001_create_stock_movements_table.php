<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de movimientos de stock de ingredientes (TAREA 2.2 / CU-003.1).
     *
     * quantity SIEMPRE positivo; el signo lo da `type`:
     * - entrada: suma stock (alta de lote / compra).
     * - salida: resta stock (descuento FEFO por venta).
     * - merma: resta stock con motivo OBLIGATORIO (desperdicio, vencimiento).
     * - ajuste: corrección manual (cantidad puede reflejar alta o baja).
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->string('type'); // entrada | salida | merma | ajuste
            $table->decimal('quantity', 10, 2);
            $table->decimal('stock_before', 10, 2)->nullable();
            $table->decimal('stock_after', 10, 2)->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Referencia polimórfica (Order, Sale, PurchaseOrder...).
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('ingredient_id');
            $table->index(['reference_type', 'reference_id']);
            $table->index('user_id');
        });

        // El signo lo da type: la cantidad nunca puede ser negativa/cero.
        // (Blueprint::check no existe en Laravel 12.35 -> constraint raw PG.)
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_positive CHECK (quantity > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};