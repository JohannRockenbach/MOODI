<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        //cantidad solicitada
        'quantity_requested',
        'unit_price',
        'purchase_order_id',
        'ingredient_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'decimal:3',
            'unit_price' => 'decimal:2',
        ];
    }

    // --- Relaciones ---

    /**
     * El detalle PERTENECE A una orden de compra.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * El detalle corresponde a UN ingrediente.
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}