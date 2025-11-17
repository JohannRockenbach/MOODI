<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'display_order',
        'settings',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Define la relación "uno a muchos" con los productos.
     * Una categoría puede tener muchos productos.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Parent category (nullable). Allows subcategories via self relation.
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Child categories (subcategories).
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected static function booted()
    {
        static::deleting(function (self $model) {
            // Prevent deleting a category that still has products or subcategories
            if ($model->products()->exists() || $model->children()->exists()) {
                throw new \Exception('No se puede eliminar la categoría porque tiene productos o subcategorías asociadas. Reasigne o elimine primero.');
            }
        });
    }
}