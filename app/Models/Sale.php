<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'total_amount',
        'payment_method',
        'status',
        'order_id',
        'cashier_id',
        'restaurant_id',
        'caja_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    // --- Relaciones ---

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class)
                    ->withPivot('amount_discounted');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Auto-assign the open Caja for the restaurant when creating a Sale.
     */
    protected static function booted(): void
    {
        static::creating(function ($sale) {
            // If caja_id is not already set and restaurant_id is present, try to find an open caja
            if (empty($sale->caja_id) && ! empty($sale->restaurant_id)) {
                $caja = Caja::where('restaurant_id', $sale->restaurant_id)
                    ->where('status', 'abierta')
                    ->latest('opening_date')
                    ->first();

                if ($caja) {
                    $sale->caja_id = $caja->id;
                }
            }
        });

        static::saving(function ($sale) {
            $max = 99999999.99;
            if (! is_null($sale->total_amount) && ($sale->total_amount < 0 || $sale->total_amount > $max)) {
                throw new \InvalidArgumentException('total_amount fuera de rango');
            }
        });
    }
}