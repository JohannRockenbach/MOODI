<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

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

        // Prevenir ciclos en la jerarquía: una categoría no puede ser hija
        // (directa o indirectamente) de sí misma.
        static::saving(function (self $model) {
            if (! $model->parent_id) {
                return;
            }

            $ancestorId = (int) $model->parent_id;

            // En edición, la propia categoría no puede ser su antecesora.
            if ($model->exists && (int) $model->id === $ancestorId) {
                throw new \Exception('Una categoría no puede ser padre de sí misma.');
            }

            // Subir por la cadena de padres; si encontramos la categoría actual
            // (o un ciclo), rechazar.
            $visited = [];
            $currentParentId = $ancestorId;

            while ($currentParentId) {
                if (in_array($currentParentId, $visited, true)) {
                    throw new \Exception('La jerarquía de categorías no puede tener ciclos.');
                }

                $visited[] = $currentParentId;

                // Si el padre es la propia categoría (en edición) → ciclo.
                if ($model->exists && (int) $model->id === $currentParentId) {
                    throw new \Exception('La jerarquía de categorías no puede tener ciclos (una categoría no puede ser descendiente de sí misma).');
                }

                $currentParentId = self::query()->whereKey($currentParentId)->value('parent_id');
            }
        });
    }
}