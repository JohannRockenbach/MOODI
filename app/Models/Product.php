<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes; // Importamos SoftDeletes

class Product extends Model
{
    use HasFactory, SoftDeletes; // Usamos SoftDeletes

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'is_available',
        'category_id',
        'restaurant_id',
        'recipe_id',
    ];

    /**
     * The attributes that should be cast.
     * Le decimos a Eloquent que trate estos campos de forma especial.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2', // Siempre tratar el precio como un decimal con 2 dígitos.
            'is_available' => 'boolean', // Tratar como verdadero/falso.
        ];
    }

    /*
    Relaciones
    */

    /**
     * Define la relación inversa "pertenece a".
     * Un producto PERTENECE A una categoría.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Un producto PERTENECE A un restaurante.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Un producto PUEDE PERTENECER A una receta.
     * La relación es opcional.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}