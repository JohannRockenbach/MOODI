<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'stock',
        'min_stock',
        'is_available',
        'category_id',
        'restaurant_id',
        'recipe_id',
        'preparation_time_minutes',
        'is_temporal',
        'critical_ingredient_id',
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
            'preparation_time_minutes' => 'integer', // Tiempo de preparación en minutos.
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

    /**
     * The orders this product appears in (through pivot order_product).
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_product')
                    ->withPivot(['id', 'quantity', 'notes'])
                    ->withTimestamps();
    }

    /**
     * Access to the pivot records directly.
     */
    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    /*
    Atributos Calculados
    */

    /**
     * Calcula el stock real del producto.
     * 
     * Para productos de venta directa (sin receta): retorna el stock almacenado.
     * Para productos elaborados (con receta): calcula cuántas unidades se pueden hacer
     * basándose en los ingredientes disponibles.
     */
    protected function realStock(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                // Si el producto NO tiene receta (ej: Coca-Cola, producto de venta directa)
                if (!$this->recipe_id) {
                    return (int) floor($this->stock ?? 0);
                }

                // Si el producto SÍ tiene receta (ej: Hamburguesa, producto elaborado)
                // Cargar la receta con sus ingredientes y las cantidades requeridas
                $recipe = $this->recipe()->with('ingredients')->first();

                // Si no existe la receta o no tiene ingredientes, retornar 0
                if (!$recipe || $recipe->ingredients->isEmpty()) {
                    return 0;
                }

                // Calcular cuántas unidades podemos hacer de este producto
                // basándonos en cada ingrediente
                $possibleUnits = [];

                foreach ($recipe->ingredients as $ingredient) {
                    $requiredAmount = $ingredient->pivot->required_amount;
                    // ACTUALIZADO: Usar el nuevo sistema de lotes
                    $availableStock = $ingredient->batches()->sum('quantity');

                    // Si el ingrediente no tiene stock o la cantidad requerida es 0, no se puede hacer
                    if ($requiredAmount <= 0) {
                        continue;
                    }

                    // Calcular cuántas unidades podemos hacer con este ingrediente
                    $units = $availableStock / $requiredAmount;
                    $possibleUnits[] = $units;
                }

                // Si no hay ingredientes válidos, retornar 0
                if (empty($possibleUnits)) {
                    return 0;
                }

                // Retornar el mínimo (cuello de botella)
                // Si puedo hacer 10 hamburguesas por el pan pero solo 4 por la carne,
                // el stock real es 4
                return (int) floor(min($possibleUnits));
            }
        );
    }

    /**
     * Relación con el ingrediente crítico (para productos temporales anti-desperdicio)
     */
    public function criticalIngredient(): BelongsTo
    {
        return $this->belongsTo(IngredientBatch::class, 'critical_ingredient_id');
    }
}