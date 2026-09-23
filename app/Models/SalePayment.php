<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desglose de pago de una venta: cada fila representa UN método y UN monto
 * dentro del SPLIT de una Sale. Sin split → una única fila con el método y
 * el total (siempre se guarda el desglose).
 */
class SalePayment extends Model
{
    protected $fillable = [
        'sale_id',
        'payment_method',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}