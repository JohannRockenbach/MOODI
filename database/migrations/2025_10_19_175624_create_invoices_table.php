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
    //Facturas.
    Schema::create('invoices', function (Blueprint $table) {
        // Clave Primaria (PK) autoincremental.
        $table->id();

        // Número de CAE (Código de Autorización Electrónico) u otro identificador fiscal.
        // Es 'nullable' porque una venta podría no requerir factura oficial inmediatamente.
        $table->string('cae_number')->nullable()->unique();

        // Datos del cliente al momento de la facturación (Nombre, CUIT/DNI, Domicilio).
        // Usamos 'json' para guardar esta información de forma flexible.
        $table->json('customer_data');

        // --- Relación con la Venta ---

        // La factura se genera a partir de una venta.
        // La relación es 1 a 1, por lo que la clave foránea debe ser única.
        $table->foreignId('sale_id')
              ->unique()
              ->constrained('sales')
              ->onUpdate('cascade')
              ->onDelete('cascade'); // Si se borra la venta, se borra la factura.

        // La columna 'created_at' servirá como la fecha de emisión de la factura.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
