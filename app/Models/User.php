<?php

namespace App\Models;

// Añadimos la importación para SoftDeletes y las relaciones
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes; // <-- 1. IMPORTANTE: Importar el Trait
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    // Usamos los Traits que nos da Laravel y añadimos el nuestro
    use HasFactory, Notifiable, SoftDeletes; // <-- 2. IMPORTANTE: Usar el Trait

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
        'phone',
        'account_status',
        'restaurant_id', // Incluimos la FK para poder asignarla masivamente
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Esto es por seguridad, para que nunca se muestren en respuestas API.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     * Le dice a Eloquent cómo tratar ciertos datos.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
     Relaciones
    */

    /**
     * Define la relación inversa "pertenece a".
     * Un usuario PERTENECE A un restaurante.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Un usuario (como mesero) puede tener muchos pedidos.
     * Usamos un nombre descriptivo para la relación.
     */
    public function waiterOrders(): HasMany
    {
        // Le indicamos explícitamente que la clave foránea en la tabla 'orders' es 'waiter_id'.
        return $this->hasMany(Order::class, 'waiter_id');
    }

    /**
     * Un usuario (como cajero) puede tener muchas ventas.
     */
    public function cashierSales(): HasMany
    {
        // Hacemos lo mismo para las ventas y la clave 'cashier_id'.
        return $this->hasMany(Sale::class, 'cashier_id');
    }
}