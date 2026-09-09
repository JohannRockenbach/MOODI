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

    /**
     * Stock actual del ingrediente = SUMA de la cantidad de sus lotes.
     * La columna current_stock fue eliminada de la tabla ingredients.
     */
    public function getTotalStockAttribute(): float
    {
        return (float) $this->batches()->sum('quantity');
    }

    /**
     * Stock DISPONIBLE del ingrediente: suma de lotes NO vencidos.
     *
     * - Si la relación batches ya está cargada (eager-load), calcula sobre la
     *   colección en memoria (sin N+1). Si no, hace una query con filtro de vencidos.
     * - Un lote con expiration_date en el pasado NO cuenta como stock sano.
     * - Un lote sin expiration_date (null) cuenta como disponible (no vencido).
     */
    public function availableStock(): float
    {
        $batches = $this->relationLoaded('batches')
            ? $this->batches
            : $this->batches()->get();

        return (float) $batches
            ->filter(fn (IngredientBatch $batch) => $batch->expiration_date === null
                || $batch->expiration_date->isFuture())
            ->sum('quantity');
    }
}