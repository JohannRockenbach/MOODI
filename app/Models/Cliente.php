<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
