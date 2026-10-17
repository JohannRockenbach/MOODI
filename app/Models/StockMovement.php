<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Movimiento de stock de un ingrediente (TAREA 2.2 / CU-003.1).
 *
 * quantity SIEMPRE positivo; el signo lo da `type`:
 * entrada | salida | merma | ajuste.
 */
class StockMovement extends Model
{
    // Solo created_at: el historial es inmutable por diseño.
    public const UPDATED_AT = null;

    public const TYPE_ENTRADA = 'entrada';

    public const TYPE_SALIDA = 'salida';

    public const TYPE_MERMA = 'merma';

    public const TYPE_AJUSTE = 'ajuste';

    protected $fillable = [
        'ingredient_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'reason',
        'user_id',
        'reference_type',
        'reference_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'stock_before' => 'decimal:2',
            'stock_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Referencia polimórfica: Order, Sale, PurchaseOrder...
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}