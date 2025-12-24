<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Provider extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        //nombre_negocio
        'business_name',
        'cuit',
        'phone',
        'email',
        'restaurant_id',
    ];
    
    /**
     * Boot method para auto-asignar restaurant_id
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($provider) {
            if (empty($provider->restaurant_id)) {
                $provider->restaurant_id = Auth::user()?->restaurant_id ?? 1;
            }
        });
    }

    /**
     * Un proveedor PERTENECE A un restaurante.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Un proveedor puede suministrar MUCHOS ingredientes.
     * Esta es una relación de Muchos a Muchos.
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class)
                    ->withPivot('purchase_price', 'purchase_unit'); // <-- ¡Esto es clave!
    }
}