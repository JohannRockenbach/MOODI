<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'restaurant_id',
        'name',
        'email',
        'phone',
        'birthday',
        'fcm_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
        ];
    }

    /**
     * Boot method para auto-asignar restaurant_id (sistema de restaurante único).
     */
    protected static function booted(): void
    {
        static::creating(function (Cliente $cliente) {
            if (empty($cliente->restaurant_id)) {
                $cliente->restaurant_id = Auth::user()?->restaurant_id ?? 1;
            }
        });
    }

    /**
     * Busca un cliente por email INCLUYENDO los soft-deleted, para poder
     * restaurarlo y reutilizar su fila (el UNIQUE(email) cuenta filas borradas).
     */
    public static function findByEmailWithTrashed(string $email): ?self
    {
        return static::withTrashed()->where('email', $email)->first();
    }

    /**
     * Un cliente está vinculado a UNA cuenta de usuario (nullable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un cliente pertenece a un restaurante.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Un cliente puede tener MUCHOS pedidos.
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}
