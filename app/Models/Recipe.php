<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'instructions',
    ];

    /**
     * Una receta está compuesta por MUCHOS ingredientes.
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class)
                    ->withPivot('required_amount');
    }

    /**
     * Una receta PERTENECE A UN producto.
     * Esto define la inversa de la relación que establecimos en el modelo Product.
     */
    public function product(): HasOne
    {
        return $this->hasOne(Product::class);
    }
}