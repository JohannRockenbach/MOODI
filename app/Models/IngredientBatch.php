<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class IngredientBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_id',
        'quantity',
        'expiration_date',
        'purchase_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expiration_date' => 'date',
            'purchase_date' => 'date',
        ];
    }

    /**
     * Relación inversa con Ingredient
     */
    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * Historial de stock (TAREA 2.2 / CU-003.1):
     * - created → movimiento `entrada` (alta de lote, incluido el lote inicial
     *   de CreateIngredient y el CreateAction de BatchesRelationManager).
     * - updated (cantidad) → movimiento `ajuste` (edición manual del lote).
     * - deleted → movimiento `ajuste` "Baja de lote".
     *
     * Los decrementos FEFO de StockDeductionService NO pasan por acá: el
     * servicio descuenta con IngredientBatch::withoutEvents() y registra su
     * propia fila `salida`. Sin esa separación cada venta escribiría un
     * `ajuste` fantasma además de la `salida`.
     *
     * Registro a prueba de fallos (mismo criterio que AuditService): si el
     * insert falla se loggea y la operación original NO se rompe.
     */
    protected static function booted(): void
    {
        static::created(function (IngredientBatch $batch) {
            if ((float) $batch->quantity <= 0) {
                return;
            }

            try {
                // Antes de este lote: suma de los demás lotes del ingrediente.
                $before = (float) $batch->ingredient()->first()?->batches()
                    ->where('id', '!=', $batch->id)
                    ->sum('quantity') ?? 0.0;

                StockMovement::create([
                    'ingredient_id' => $batch->ingredient_id,
                    'type' => StockMovement::TYPE_ENTRADA,
                    'quantity' => round((float) $batch->quantity, 2),
                    'stock_before' => round($before, 2),
                    'stock_after' => round($before + (float) $batch->quantity, 2),
                    'reason' => 'Alta de lote'.($batch->notes ? ' · '.$batch->notes : ''),
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('StockMovement: no se pudo registrar la entrada de lote', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::updated(function (IngredientBatch $batch) {
            if (! $batch->wasChanged('quantity')) {
                return;
            }

            try {
                $oldQuantity = (float) $batch->getOriginal('quantity');
                $newQuantity = (float) $batch->quantity;

                if ($oldQuantity == $newQuantity) {
                    return;
                }

                // Suma de los demás lotes (inalterada por este evento).
                $others = (float) $batch->ingredient()->first()?->batches()
                    ->where('id', '!=', $batch->id)
                    ->sum('quantity') ?? 0.0;

                StockMovement::create([
                    'ingredient_id' => $batch->ingredient_id,
                    'type' => StockMovement::TYPE_AJUSTE,
                    'quantity' => round(abs($newQuantity - $oldQuantity), 2),
                    'stock_before' => round($others + $oldQuantity, 2),
                    'stock_after' => round($others + $newQuantity, 2),
                    'reason' => 'Ajuste manual de lote',
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('StockMovement: no se pudo registrar el ajuste de lote', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::deleted(function (IngredientBatch $batch) {
            if ((float) $batch->quantity <= 0) {
                return;
            }

            try {
                $before = (float) $batch->ingredient()->first()?->batches()
                    ->where('id', '!=', $batch->id)
                    ->sum('quantity') ?? 0.0;

                StockMovement::create([
                    'ingredient_id' => $batch->ingredient_id,
                    'type' => StockMovement::TYPE_AJUSTE,
                    'quantity' => round((float) $batch->quantity, 2),
                    'stock_before' => round($before + (float) $batch->quantity, 2),
                    'stock_after' => round($before, 2),
                    'reason' => 'Baja de lote',
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('StockMovement: no se pudo registrar la baja de lote', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
