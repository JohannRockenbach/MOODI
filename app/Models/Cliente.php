<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
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
     * Un cliente puede tener MUCHOS pedidos.
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}
