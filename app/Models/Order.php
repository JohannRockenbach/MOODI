<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'type',
        'table_id',
        'waiter_id',
        'restaurant_id',
    ];

    /*
     Relaciones
    */

    /**
     * Un pedido se realiza en UNA mesa.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    /**
     * Un pedido es tomado por UN mozo (usuario).
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * Un pedido PERTENECE A un restaurante.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Un pedido puede tener MUCHOS productos.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
                    ->withPivot('quantity', 'notes') // Traemos la info extra de la tabla pivote.
                    ->withTimestamps(); // También traemos created_at/updated_at de la tabla pivote.
    }

    /**
     * Un pedido genera UNA venta.
     */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }
}