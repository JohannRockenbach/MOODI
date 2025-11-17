<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'min_stock',
        //medicion
        'measurement_unit',
        'reorder_point',
        'restaurant_id',
    ];

    protected function casts(): array
    {
        return [
            'reorder_point' => 'decimal:3',
        ];
    }

    /*
     Relaciones
    */

    /**
     * Un ingrediente PERTENECE A un restaurante.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Un ingrediente puede ser suministrado por MUCHOS proveedores.
     */
    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class)
                    ->withPivot('purchase_price', 'purchase_unit');
    }

    /**
     * Un ingrediente puede ser parte de MUCHAS recetas.
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class)
                    ->withPivot('required_amount');
    }

    /**
     * Un ingrediente tiene MUCHOS lotes.
     */
    public function batches()
    {
        return $this->hasMany(IngredientBatch::class);
    }
}