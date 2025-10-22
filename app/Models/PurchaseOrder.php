<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'expected_delivery_date',
        'status',
        'provider_id',
        'requester_id',
        'restaurant_id',
    ];

    protected function casts(): array
    {
        return [
            'expected_delivery_date' => 'date', // Tratar como objeto de fecha.
        ];
    }

    // --- Relaciones ---

    /**
     * Una orden de compra PERTENECE A un proveedor.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Una orden de compra es solicitada por UN usuario.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Una orden de compra PERTENECE A un restaurante.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Una orden de compra tiene MUCHOS detalles (líneas de ingredientes).
     */
    public function details(): HasMany
    {
        return $this->hasMany(PurchaseOrderDetail::class);
    }
}