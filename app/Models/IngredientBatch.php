<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
