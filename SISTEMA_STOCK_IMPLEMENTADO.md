# 📦 Sistema de Gestión de Stock Implementado - MOODI

## ✅ Implementación Completa

### 🗄️ **Tarea 1: Migraciones de Base de Datos**

#### **Migración: `add_min_stock_to_ingredients_table`**
```php
Schema::table('ingredients', function (Blueprint $table) {
    $table->decimal('min_stock', 8, 2)->default(0)->after('current_stock');
});
```
- **Campo agregado**: `min_stock` en tabla `ingredients`
- **Tipo**: `decimal(8, 2)` - permite 6 dígitos enteros y 2 decimales
- **Default**: `0`
- **Posición**: Después de `current_stock`

#### **Migración: `add_min_stock_to_products_table`**
```php
Schema::table('products', function (Blueprint $table) {
    $table->decimal('stock', 8, 2)->default(0)->after('price');  // Ya existía
    $table->decimal('min_stock', 8, 2)->default(0)->after('stock');
});
```
- **Campo agregado**: `min_stock` en tabla `products`
- **Nota**: Campo `stock` ya existía previamente
- **Tipo**: `decimal(8, 2)`
- **Default**: `0`

---

### 🧮 **Tarea 2: Atributo `real_stock` en Modelo Product**

Implementado en `app/Models/Product.php`:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function realStock(): Attribute
{
    return Attribute::make(
        get: function (): int {
            // Producto de venta directa (sin receta)
            if (!$this->recipe_id) {
                return (int) floor($this->stock ?? 0);
            }

            // Producto elaborado (con receta)
            $recipe = $this->recipe()->with('ingredients')->first();

            if (!$recipe || $recipe->ingredients->isEmpty()) {
                return 0;
            }

            // Calcular unidades basadas en ingredientes
            $possibleUnits = [];

            foreach ($recipe->ingredients as $ingredient) {
                $requiredAmount = $ingredient->pivot->required_amount;
                $availableStock = $ingredient->current_stock;

                if ($requiredAmount <= 0) {
                    continue;
                }

                $units = $availableStock / $requiredAmount;
                $possibleUnits[] = $units;
            }

            if (empty($possibleUnits)) {
                return 0;
            }

            // Retornar el mínimo (cuello de botella)
            return (int) floor(min($possibleUnits));
        }
    );
}
```

**Uso:**
```php
$product = Product::find(1);
$stockReal = $product->real_stock; // Acceso como propiedad
```

**Lógica:**

1. **Productos de Venta Directa** (sin `recipe_id`):
   - Retorna el valor de la columna `stock`
   - Ej: Coca-Cola con `stock = 15` → `real_stock = 15`

2. **Productos Elaborados** (con `recipe_id`):
   - Carga la receta con sus ingredientes
   - Para cada ingrediente calcula: `stock_disponible / cantidad_requerida`
   - Retorna el **mínimo** de todos los cálculos (cuello de botella)
   - Ej: Hamburguesa necesita:
     - 0.1 kg harina (disponible: 2.5 kg) → 25 unidades
     - 0.2 kg carne (disponible: 0.5 kg) → 2 unidades
     - **Stock real = 2** (limitado por la carne)

---

### 📊 **Tarea 3: Widget `LowStockWidget` (Filament v3)**

**Archivo**: `app/Filament/Widgets/LowStockWidget.php`

**Características:**
- ✅ Widget personalizado de Filament v3
- ✅ Vista Blade custom: `resources/views/filament/widgets/low-stock-widget.blade.php`
- ✅ Muestra **Ingredientes** y **Productos** con stock bajo en una sola tabla
- ✅ Solo muestra productos de venta directa (sin receta)
- ✅ Ordenamiento por diferencia (más críticos primero)
- ✅ Formato español (comas para decimales, puntos para miles)
- ✅ Colores dinámicos según criticidad
- ✅ Iconos diferenciados (beaker para ingredientes, cube para productos)

**Consulta del Widget:**

```php
public function getLowStockData(): array
{
    // Ingredientes con stock bajo
    $ingredients = Ingredient::query()
        ->whereColumn('current_stock', '<=', 'min_stock')
        ->with('restaurant')
        ->get();

    // Productos de venta directa con stock bajo
    $products = Product::query()
        ->whereNull('recipe_id') // Solo venta directa
        ->whereColumn('stock', '<=', 'min_stock')
        ->with('restaurant')
        ->get();

    // Combinar y ordenar
    return $ingredients->merge($products)
        ->sortBy('difference')
        ->values()
        ->toArray();
}
```

**Columnas de la Tabla:**
1. **Nombre**: Con icono (beaker/cube) según tipo
2. **Stock Actual**: Coloreado (rojo si ≤0, naranja si ≤50% del mínimo)
3. **Stock Mínimo**: Badge azul
4. **Diferencia**: Badge rojo/verde según si es negativa o positiva
5. **Tipo**: Badge "Ingrediente" o "Producto"
6. **Restaurante**: Nombre del restaurante asociado

**Registrado en**: `app/Providers/Filament/AdminPanelProvider.php`

---

## 🧪 Pruebas Realizadas

### **Test 1: Ingredientes con Stock Bajo**
```
Harina:
  Stock Actual: 2.5 kg
  Stock Mínimo: 10.0 kg
  Diferencia: -7.5 kg ❌

Carne Molida:
  Stock Actual: 0.5 kg
  Stock Mínimo: 5.0 kg
  Diferencia: -4.5 kg ❌
```

### **Test 2: Productos de Venta Directa con Stock Bajo**
```
Coca-Cola 500ml:
  Stock Actual: 3.0
  Stock Mínimo: 20.0
  Diferencia: -17.0 ❌

Agua Mineral 500ml:
  Stock Actual: 0.0
  Stock Mínimo: 15.0
  Diferencia: -15.0 ❌
```

### **Test 3: Stock Real de Producto Elaborado**
```
Hamburguesa Clásica (con receta):
  Ingredientes:
    - Harina: 2.5 kg disponibles / 0.1 kg requeridos = 25 unidades posibles
    - Carne: 0.5 kg disponibles / 0.2 kg requeridos = 2 unidades posibles
  
  Stock Real Calculado: 2 unidades ✅
  (Limitado por la carne, que es el cuello de botella)
```

---

## 🎯 Funcionalidades Clave

### ✅ **Stock de Productos de Venta Directa**
- Campo `stock` en tabla `products`
- Gestión manual del inventario (ej: Coca-Cola, Agua, productos empaquetados)
- Umbral mínimo `min_stock` para alertas

### ✅ **Stock Calculado de Productos Elaborados**
- Productos con `recipe_id` (ej: Hamburguesas, Pizzas, Platos elaborados)
- Stock calculado automáticamente basado en ingredientes disponibles
- Algoritmo de "cuello de botella" (mínimo entre todos los ingredientes)
- No requiere campo `stock` manual

### ✅ **Alertas de Stock Bajo**
- Widget en Dashboard muestra ítems críticos
- Comparación: `current_stock <= min_stock`
- Ordenamiento por diferencia (más urgentes primero)
- Filtro inteligente: solo productos de venta directa (sin receta)

### ✅ **Integración con Restaurantes**
- Cada ingrediente y producto pertenece a un restaurante
- Widget muestra el restaurante asociado
- Permite gestión multi-restaurante

---

## 📁 Archivos Modificados/Creados

### **Migraciones**
- ✅ `database/migrations/2025_10_30_030142_add_min_stock_to_ingredients_table.php`
- ✅ `database/migrations/2025_10_30_030229_add_min_stock_to_products_table.php`

### **Modelos**
- ✅ `app/Models/Product.php` - Agregado atributo `real_stock`
- ✅ `app/Models/Ingredient.php` - Agregado `min_stock` a fillable

### **Widgets**
- ✅ `app/Filament/Widgets/LowStockWidget.php`
- ✅ `resources/views/filament/widgets/low-stock-widget.blade.php`

### **Providers**
- ✅ `app/Providers/Filament/AdminPanelProvider.php` - Widget registrado

### **Recursos (Fix)**
- ✅ `app/Filament/Resources/OrderResource.php` - Fix para productos eliminados

---

## 🚀 Cómo Usar

### **1. Acceder al Dashboard**
- Ir a `/admin`
- El widget "Stock Bajo - Alerta de Inventario" aparece automáticamente

### **2. Gestionar Ingredientes**
- Ir a `Ingredientes` en el panel
- Configurar `current_stock` y `min_stock` para cada ingrediente
- El widget alertará cuando `current_stock <= min_stock`

### **3. Gestionar Productos de Venta Directa**
- Ir a `Productos` en el panel
- Para productos SIN receta (ej: Bebidas, productos empaquetados):
  - Configurar `stock` manualmente
  - Configurar `min_stock` para alertas
  - Dejar `recipe_id` en `NULL`

### **4. Crear Productos Elaborados**
- Crear una `Recipe` primero
- Asociar ingredientes con cantidades requeridas (`required_amount`)
- Crear un `Product` con `recipe_id` apuntando a la receta
- El `stock` se ignorará, se usará `real_stock` calculado automáticamente

### **5. Consultar Stock Real**
```php
// En código
$product = Product::find(1);
$stockDisponible = $product->real_stock;

// En Tinker
$product = App\Models\Product::find(1);
echo "Stock real: {$product->real_stock}";
```

---

## 💡 Mejoras Futuras Sugeridas

1. **Notificaciones Push**: Alertar cuando stock llegue a niveles críticos
2. **Historial de Stock**: Registrar movimientos de inventario
3. **Reabastecimiento Automático**: Generar órdenes de compra automáticas
4. **Predicción de Demanda**: ML para calcular stock óptimo
5. **Integración con POS**: Descontar stock automáticamente al vender

---

## 📝 Comandos Útiles

```bash
# Ver migraciones pendientes
php artisan migrate:status

# Ejecutar migraciones
php artisan migrate

# Limpiar cachés
php artisan view:clear
php artisan cache:clear
php artisan filament:cache-components

# Verificar datos en Tinker
php artisan tinker
> $product = App\Models\Product::find(1);
> $product->real_stock;
```

---

¡Sistema de Stock implementado y funcionando correctamente! 🎉
