<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restaurant extends Model
{
    use HasFactory, SoftDeletes;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'address',
        'cuit',
        'schedules',
        'contact_phone',
        'marketing_settings',
    ];

    protected $casts = [
        'marketing_settings' => 'array',
    ];

    /*
    | Relaciones de Administración y Núcleo
    */

    /**
     * Un restaurante puede tener muchos usuarios (empleados, clientes, etc.).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Un restaurante tiene muchos proveedores.
     */
    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class); // Asumimos que el modelo se llamará Provider
    }

    /**
     * Un restaurante gestiona muchos ingredientes en su inventario.
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class); // Asumimos que el modelo se llamará Ingredient
    }

    /**
     * Un restaurante tiene un catálogo de muchos productos.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class); // Asumimos que el modelo se llamará Product
    }

    /*
     Relaciones Operacionales
    */

    /**
     * Un restaurante tiene muchas mesas.
     */
    public function tables(): HasMany
    {
        return $this->hasMany(Table::class); // Asumimos que el modelo se llamará Table
    }

    /**
     * Un restaurante gestiona muchas reservas.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class); // Asumimos que el modelo se llamará Reservation
    }

    /**
     * En un restaurante se generan muchos pedidos (órdenes).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class); // Asumimos que el modelo se llamará Order
    }

    /**
     * Un restaurante emite muchas órdenes de compra.
     * Nota: El método es camelCase 'purchaseOrders' para la tabla 'purchase_orders'.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class); // Asumimos que el modelo se llamará PurchaseOrder
    }


    /*
     Relaciones Financieras
    */

    /**
     * Un restaurante registra muchas ventas.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class); // Asumimos que el modelo se llamará Sale
    }

    /**
     * Un restaurante puede ofrecer muchos tipos de descuentos.
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class); // Asumimos que el modelo se llamará Discount
    }
}