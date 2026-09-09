<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    use HasFactory;

    // Estados de mesa usados por la app (valores en inglés; en DB pueden existir
    // valores legacy en español de migraciones viejas, no se tocan sin migración).
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_MAINTENANCE = 'maintenance';

    /**
     * Scope: mesas disponibles (status = available).
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    /**
     * Scope: mesas ocupadas (status = occupied).
     */
    public function scopeOccupied($query)
    {
        return $query->where('status', self::STATUS_OCCUPIED);
    }

    /**
     * Scope: mesas reservadas (status = reserved).
     */
    public function scopeReserved($query)
    {
        return $query->where('status', self::STATUS_RESERVED);
    }

    protected $fillable = [
        'number',
        'capacity',
        'location',
        'status',
        //Mesero
        'waiter_id',
        'restaurant_id',
        // Coordenadas para el floor plan
        'pos_x',
        'pos_y',
    ];

    /*
     Relaciones
    */

    /**
     * Una mesa PERTENECE A un restaurante.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Una mesa PUEDE TENER asignado un mozo (que es un usuario).
     */
    public function waiter(): BelongsTo
    {
        //Aca le especifico a laravel el nombre de la columna de la fk esto es porque no tengo una tabla "waiter" practicamente
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * Alias para la relación waiter (usado en Filament)
     * Permite usar tanto $table->user como $table->waiter
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * Una mesa puede tener MUCHAS reservas asociadas.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * En una mesa se pueden generar MUCHOS pedidos.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}