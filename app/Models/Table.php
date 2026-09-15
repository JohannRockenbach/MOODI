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

    /**
     * Estados de pedido que cuentan como "activos" y mantienen la mesa ocupada.
     */
    public const ORDER_OPEN_STATUSES = ['pending', 'processing', 'ready_for_pickup'];

    /**
     * Estados de reserva que consideramos "futuras/activas" para no liberar la mesa
     * mientras haya una reserva vigente por delante.
     */
    public const RESERVATION_ACTIVE_STATUSES = ['pending', 'confirmed'];

    /**
     * ¿La mesa tiene pedidos (no cancelados ni completados) activos?
     *
     * Un pedido activo implica que la mesa sigue en uso: se avala que el estado
     * sea 'occupied' y NO se libera hasta que no queden pedidos activos.
     */
    public function hasActiveOrders(): bool
    {
        return $this->orders()
            ->whereIn('status', self::ORDER_OPEN_STATUSES)
            ->exists();
    }

    /**
     * ¿La mesa tiene reservas activas (pending/confirmed) programadas a FUTURO?
     *
     * Al liberar una mesa ocupada (post-cobro), si quedan reservas vigentes
     * por delante la mesa NO debe volver a 'available': debe pasar a 'reserved'
     * para que siga bloqueada para próximos clientes.
     */
    public function hasFutureActiveReservations(): bool
    {
        return $this->reservations()
            ->whereIn('status', self::RESERVATION_ACTIVE_STATUSES)
            ->where('reservation_time', '>', now())
            ->exists();
    }

    /**
     * Marcar la mesa como OCUPADA.
     *
     * Solo avanza desde 'available' o 'reserved'. NUNCA pisa una mesa
     * 'occupied' (ya en uso) ni 'maintenance' (fuera de servicio).
     *
     * Devuelve true si la mesa quedó ocupada, false si no se pudo (ya ocupada
     * o en mantenimiento).
     */
    public function occupy(): bool
    {
        if (! in_array($this->status, [
            self::STATUS_AVAILABLE,
            self::STATUS_RESERVED,
        ], true)) {
            return false;
        }

        $this->fill(['status' => self::STATUS_OCCUPIED])->saveQuietly();

        return true;
    }

    /**
     * Liberar la mesa de forma segura.
     *
     * Solo se libera cuando la mesa está 'occupied'. Si aún tiene pedidos
     * activos (no cobrados/cancelados) NO se libera. Si quedan reservas
     * futuras vigentes pasa a 'reserved' (no a 'available'); si no, a
     * 'available'.
     *
     * Devuelve true si se liberó, false si se denegó.
     */
    public function release(): bool
    {
        if ($this->status !== self::STATUS_OCCUPIED) {
            return false;
        }

        if ($this->hasActiveOrders()) {
            return false;
        }

        $nextStatus = $this->hasFutureActiveReservations()
            ? self::STATUS_RESERVED
            : self::STATUS_AVAILABLE;

        $this->fill(['status' => $nextStatus])->saveQuietly();

        return true;
    }
}