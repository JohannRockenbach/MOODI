# 📋 Guía de Uso de Factories para MOODI

## 🎯 Factories Creados

1. **ProductFactory** - Genera productos con nombres realistas de comida
2. **OrderProductFactory** - Genera ítems de pedidos (productos en pedidos)
3. **OrderFactory** - Genera pedidos completos con productos automáticamente

---

## 🚀 Uso Básico

### 1. Crear un Pedido Simple (con productos automáticos)

```php
use App\Models\Order;

// Crea 1 pedido con 1-5 productos aleatorios
$order = Order::factory()->create();

// Crea 10 pedidos con productos
Order::factory()->count(10)->create();
```

**¿Qué hace?** 
- Crea el pedido
- Automáticamente crea entre 1-5 productos disponibles
- Crea los registros en `order_product` conectando el pedido con los productos
- Calcula las cantidades aleatorias (1-3 unidades por producto)

---

### 2. Crear Productos Independientes

```php
use App\Models\Product;

// Crear 1 producto
$product = Product::factory()->create();

// Crear 20 productos
Product::factory()->count(20)->create();

// Crear producto disponible
Product::factory()->available()->create();

// Crear producto NO disponible
Product::factory()->unavailable()->create();

// Crear producto para una categoría específica
Product::factory()->forCategory(1)->create();
```

---

### 3. Crear Pedidos con Estado Específico

```php
use App\Models\Order;

// Pedido pendiente
Order::factory()->pending()->create();

// Pedido en proceso
Order::factory()->inProgress()->create();

// Pedido servido
Order::factory()->served()->create();

// Pedido pagado
Order::factory()->paid()->create();

// Pedido cancelado
Order::factory()->cancelled()->create();
```

---

### 4. Crear Pedidos con Tipo Específico

```php
use App\Models\Order;

// Pedido delivery (sin mesa)
Order::factory()->delivery()->create();

// Pedido local (con mesa)
Order::factory()->local()->create();

// Pedido para llevar (sin mesa)
Order::factory()->takeaway()->create();
```

---

### 5. Combinar Estados y Tipos

```php
use App\Models\Order;

// Pedido delivery pendiente
Order::factory()->delivery()->pending()->create();

// Pedido local servido
Order::factory()->local()->served()->create();

// 5 pedidos para llevar pagados
Order::factory()->takeaway()->paid()->count(5)->create();
```

---

## 🎨 Casos de Uso Avanzados

### Crear Pedidos para un Restaurante Específico

```php
use App\Models\Restaurant;
use App\Models\Order;

// Supongamos que tienes un restaurante existente
$restaurant = Restaurant::find(1);

// Crear 10 pedidos para ese restaurante
Order::factory()
    ->forRestaurant($restaurant->id)
    ->count(10)
    ->create();

// Los productos se crearán automáticamente para ese restaurante
```

---

### Crear Pedidos para una Mesa Específica

```php
use App\Models\Table;
use App\Models\Order;

$table = Table::find(5);

Order::factory()
    ->forTable($table->id)
    ->local() // Tipo local
    ->inProgress() // En proceso
    ->create();
```

---

### Crear Pedidos para un Mozo Específico

```php
use App\Models\User;
use App\Models\Order;

$mozo = User::find(2);

Order::factory()
    ->forWaiter($mozo->id)
    ->count(5)
    ->create();
```

---

### Crear un Pedido Completo Personalizado

```php
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;

$restaurant = Restaurant::find(1);
$table = Table::find(3);
$mozo = User::find(2);

Order::factory()
    ->forRestaurant($restaurant->id)
    ->forTable($table->id)
    ->forWaiter($mozo->id)
    ->local()
    ->served()
    ->create();
```

---

## 🧪 Uso en Seeders

### DatabaseSeeder.php

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear restaurantes
        $restaurants = Restaurant::factory()->count(2)->create();

        // 2. Crear categorías
        $categories = Category::factory()->count(5)->create();

        // 3. Crear productos para cada restaurante
        foreach ($restaurants as $restaurant) {
            Product::factory()
                ->count(15)
                ->forRestaurant($restaurant->id)
                ->create();
        }

        // 4. Crear usuarios (mozos)
        $mozos = User::factory()->count(5)->create();

        // 5. Crear mesas para cada restaurante
        foreach ($restaurants as $restaurant) {
            Table::factory()
                ->count(10)
                ->create(['restaurant_id' => $restaurant->id]);
        }

        // 6. Crear pedidos realistas
        foreach ($restaurants as $restaurant) {
            // 20 pedidos en proceso
            Order::factory()
                ->forRestaurant($restaurant->id)
                ->inProgress()
                ->count(20)
                ->create();

            // 30 pedidos servidos
            Order::factory()
                ->forRestaurant($restaurant->id)
                ->served()
                ->count(30)
                ->create();

            // 10 pedidos pendientes
            Order::factory()
                ->forRestaurant($restaurant->id)
                ->pending()
                ->count(10)
                ->create();

            // 5 pedidos delivery
            Order::factory()
                ->forRestaurant($restaurant->id)
                ->delivery()
                ->count(5)
                ->create();
        }
    }
}
```

---

## 🧪 Uso en Tinker

```bash
php artisan tinker
```

Luego ejecuta:

```php
// Crear 1 pedido rápido
Order::factory()->create();

// Crear 5 pedidos delivery
Order::factory()->delivery()->count(5)->create();

// Ver productos de un pedido
$order = Order::first();
$order->orderProducts; // Ver los ítems
$order->products; // Ver los productos

// Calcular total manualmente
$order->orderProducts->sum(function($op) {
    return $op->quantity * $op->product->price;
});
```

---

## ⚠️ Notas Importantes

### Campo `total_amount` en Order

Como tu modelo `Order` **NO tiene el campo `total_amount`** en la migración actual, el factory tiene el cálculo comentado:

```php
// $order->update([
//     'total_amount' => $totalAmount,
// ]);
```

**Opciones:**

1. **Calcular total dinámicamente** (actual):
   ```php
   $total = $order->orderProducts->sum(function($op) {
       return $op->quantity * $op->product->price;
   });
   ```

2. **Agregar campo a la migración** (recomendado):
   
   Crea una migración:
   ```bash
   php artisan make:migration add_total_amount_to_orders_table
   ```
   
   Contenido:
   ```php
   public function up()
   {
       Schema::table('orders', function (Blueprint $table) {
           $table->decimal('total_amount', 10, 2)->default(0);
       });
   }
   ```
   
   Luego descomenta las líneas en `OrderFactory.php`:
   ```php
   $order->update([
       'total_amount' => $totalAmount,
   ]);
   ```

---

## 🎯 Ventajas de Esta Implementación

✅ **Pedidos Realistas**: Cada pedido tiene productos reales con cantidades
✅ **Total Calculado**: El total se calcula desde los productos reales, no es un número aleatorio
✅ **Reutiliza Productos**: Si existen productos disponibles, los usa; si no, crea nuevos
✅ **Flexible**: Puedes crear pedidos simples o complejos con muchas opciones
✅ **Estados Múltiples**: Métodos helper para cada estado y tipo de pedido
✅ **Notas Realistas**: Genera notas como "Sin cebolla", "Extra queso", etc.

---

## 📝 Ejemplo Completo de Testing

```php
use App\Models\Order;
use App\Models\Restaurant;

// Crear un restaurante con pedidos de prueba
$restaurant = Restaurant::factory()->create();

// Crear 50 pedidos variados para el restaurante
collect([
    ['status' => 'pending', 'count' => 10],
    ['status' => 'en_proceso', 'count' => 15],
    ['status' => 'servido', 'count' => 20],
    ['status' => 'cancelado', 'count' => 5],
])->each(function ($config) use ($restaurant) {
    Order::factory()
        ->forRestaurant($restaurant->id)
        ->state(['status' => $config['status']])
        ->count($config['count'])
        ->create();
});

// Resultado: 50 pedidos con productos reales y totales calculados
```

---

¡Listo! Ahora puedes generar datos de prueba realistas para tu sistema de pedidos MOODI 🚀
