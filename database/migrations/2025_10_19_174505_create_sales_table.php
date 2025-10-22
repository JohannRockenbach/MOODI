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
    //Ventas.
    Schema::create('sales', function (Blueprint $table) {
        $table->id();

        // Monto total de la venta, después de descuentos.
        $table->decimal('total_amount', 10, 2);

        // Método de pago utilizado (Ej: 'efectivo', 'tarjeta_credito', 'transferencia').
        $table->string('payment_method');

        // --- Relaciones (Claves Foráneas) ---

        // El pedido que generó esta venta. Cada pedido solo puede tener una venta asociada.
        // Hacemos que la clave foránea sea única para garantizar esta regla.
        $table->foreignId('order_id')
              ->unique()
              ->constrained('orders')
              ->onUpdate('cascade');

        // El usuario (cajero) que procesó la venta.
        // Usamos 'set null' para no perder el registro de la venta si el usuario se elimina.
        $table->foreignId('cashier_id')
              ->nullable()
              ->constrained('users')
              ->onUpdate('cascade')
              ->onDelete('set null');

        // La venta pertenece a un restaurante.
        $table->foreignId('restaurant_id')
              ->constrained('restaurants')
              ->onUpdate('cascade')
              ->onDelete('cascade');

        // La columna 'created_at' servirá como la fecha y hora en que se realizó la venta.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
