<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_date',
        'closing_date',
        'initial_balance',
        'final_balance',
        'total_sales',
        'status',
        'description',
        'notes', // Campo para notas adicionales
        'opening_user_id',
        'closing_user_id',
        'restaurant_id',
    ];

    protected $casts = [
        'opening_date' => 'datetime',
        'closing_date' => 'datetime',
        'initial_balance' => 'decimal:2',
        'final_balance' => 'decimal:2',
        'total_sales' => 'decimal:2',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function openingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opening_user_id');
    }

    public function closingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closing_user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    protected static function booted()
    {
        static::saving(function (self $model) {
            // enforce DB decimal(10,2) limits and non-negative balances
            $max = 99999999.99;

            // Prefer throwing a ValidationException so Filament/Laravel display the
            // error as a field validation error (red helper) instead of an uncaught
            // exception page. Use withMessages to attach the error to the field.
            $messages = [];

            if (! is_null($model->initial_balance) && ($model->initial_balance < 0 || $model->initial_balance > $max)) {
                $messages['initial_balance'] = ['El saldo inicial está fuera de rango (0 - ' . number_format($max, 2, ',', '.') . ').'];
            }

            if (! is_null($model->final_balance) && ($model->final_balance < 0 || $model->final_balance > $max)) {
                $messages['final_balance'] = ['El saldo final está fuera de rango (0 - ' . number_format($max, 2, ',', '.') . ').'];
            }

            if (! empty($messages)) {
                throw \Illuminate\Validation\ValidationException::withMessages($messages);
            }
        });
    }

    /**
     * If total_sales is not stored, compute it on the fly from related sales.
     */
    public function getTotalSalesAttribute($value)
    {
        if (! is_null($value)) {
            return $value;
        }

        return $this->sales()->sum('total_amount');
    }
}
