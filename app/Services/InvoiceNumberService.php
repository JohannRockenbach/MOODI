<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Generador de número de comprobante correlativo (CU-001.12).
 *
 * Formato: {sucursal 4 dígitos}-{correlativo 8 dígitos} → "0001-00000001".
 * El correlativo se cuenta por restaurante y por año de emisión (created_at
 * del Invoice) y NO es un CAE fiscal: se reutiliza `cae_number` (la única
 * columna string UNIQUE de invoices) como número de comprobante básico.
 *
 * Concurrencia: se serializa con lockForUpdate() sobre las filas de venta del
 * restaurante. El índice UNIQUE de `cae_number` actúa como backstop final ante
 * cualquier carrera residual. Para que el lock cubra el insert, el llamador
 * debe invocar este método DENTRO de su propia transacción (IssueInvoiceAction
 * lo hace); la transacción anidada de este método usa un savepoint y no libera
 * el lock hasta que la transacción externa haga commit.
 */
class InvoiceNumberService
{
    public static function nextNumber(int $restaurantId, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($restaurantId, $year): string {
            // Serializa emisiones concurrentes del mismo restaurante.
            DB::table('sales')
                ->where('restaurant_id', $restaurantId)
                ->lockForUpdate()
                ->pluck('id');

            $count = Invoice::query()
                ->whereHas('sale', fn ($q) => $q->where('restaurant_id', $restaurantId))
                ->whereYear('created_at', $year)
                ->count();

            $correlativo = $count + 1;

            return sprintf('%04d-%08d', $restaurantId, $correlativo);
        });
    }
}
