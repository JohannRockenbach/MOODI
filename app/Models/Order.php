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
        'notes',
        'table_id',
        'waiter_id',
        'restaurant_id',
        'delivery_address',
        'delivery_phone',
        'customer_name',
        'stock_deducted',
        'customer_id',
    ];

    protected $casts = [
        'stock_deducted' => 'boolean',
    ];

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
     * Alias de waiter() para compatibilidad.
     * Un pedido pertenece a un usuario (el mozo).
     */
    public function user(): BelongsTo
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
        return $this->belongsToMany(Product::class, 'order_product')
                    ->withPivot('quantity', 'price', 'notes')
                    ->withTimestamps();
    }

    /**
     * Direct access to the pivot records (order_product).
     */
    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    /**
     * Un pedido genera UNA venta.
     */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    /**
     * Un pedido puede pertenecer a UN cliente (opcional).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'customer_id');
    }
}