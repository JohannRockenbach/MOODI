# Prompt Combinado: Optimización de Recursos Filament con Vistas Expandibles

## Contexto del Proyecto
Sistema de gestión de restaurante en Laravel 12 + Filament v3.3.43 con PostgreSQL.

---

## PARTE 1: Categorías - Ver Productos por Categoría

### Objetivo
Implementar un botón en `CategoryResource` que muestre todos los productos de una categoría en un modal/panel lateral.

### Requisitos
1. **Traducir formulario al español**: Todos los labels (Name→Nombre, Parent category→Categoría padre, Display order→Orden de visualización, Description→Descripción)

2. **Eager Loading**: Modificar `getEloquentQuery()` para cargar `products` y evitar consultas N+1

3. **Botón "Ver Productos"**: 
   - Solo visible si la categoría tiene productos
   - Abre un slideOver (panel lateral)
   - Ícono: `heroicon-o-cube`

4. **Vista del Modal** (`resources/views/filament/categories/products-modal.blade.php`):
   - Resumen superior con total de productos y valor total
   - Por cada producto mostrar:
     * Nombre con badge de disponibilidad
     * Descripción
     * SKU, Stock, Fecha de creación
     * Precio grande y destacado (formato español: $ X.XXX,XX)
     * Costo y margen de ganancia (si aplica)
   - Resumen final en tarjetas

5. **Mejoras de tabla**:
   - Columna "Productos" con badge verde si tiene productos
   - Mostrar si es subcategoría en la descripción
   - Fechas en formato español (d/m/Y H:i)

### Modelo Category (Relaciones existentes)
```php
public function products(): HasMany
{
    return $this->hasMany(Product::class);
}

public function parent()
{
    return $this->belongsTo(self::class, 'parent_id');
}

public function children()
{
    return $this->hasMany(self::class, 'parent_id');
}
```

---

## PARTE 2: Pedidos (Orders) - Filas Expandibles con Detalle de Productos

### Objetivo
Implementar filas expandibles en `OrderResource` que muestren los productos del pedido en una tabla detallada.

### Requisitos

#### 1. Modificar OrderResource.php

**A. Eager Loading (Optimización N+1):**
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['user', 'table', 'orderProducts.product']);
}
```

**B. Configurar tabla con filas expandibles:**
- Usar `->collapsible()` en la tabla
- Usar `->content()` para renderizar vista Blade
- Vista: `filament.tables.order-products-detail`
- Pasar variable `$record` (el pedido)

**C. Mejorar columnas:**
- ID: sortable y searchable
- Estado: badge con colores según estado (en_proceso→warning, servido→success, pagado→info, cancelado→danger)
- Tipo: badge con traducción español
- Mesa: badge primary
- Mozo: desde relación `waiter.name` o `user.name`
- **Nueva columna "Productos"**: badge con conteo de productos
- **Nueva columna "Total"**: calcular desde `orderProducts` con formato español
- Fecha: formato d/m/Y H:i

#### 2. Crear Vista Blade

**Archivo:** `resources/views/filament/tables/order-products-detail.blade.php`

**Debe incluir:**
- **Encabezado** con título "Detalle del Pedido #X" y datos de mesa/mozo
- **Tabla HTML** con 4 columnas:
  * Producto (nombre + icono + notas si existen)
  * Cantidad (badge)
  * Precio Unitario (formato español)
  * Subtotal (calculado: cantidad × precio)
- **Footer de tabla** con Total del Pedido en negrita
- **Resumen inferior** con 3 tarjetas:
  * Total Productos (distintos)
  * Total Items (suma cantidades)
  * Estado actual
- **Manejo de pedidos vacíos**: mensaje con icono si no hay productos
- **Estilos**: Tailwind + modo oscuro (`dark:` variants)
- **Colores**: bg-gray-50/dark:bg-gray-900/50, bordes gray-200/dark:gray-700

#### 3. Modelo Order (Relaciones existentes)

```php
public function waiter(): BelongsTo
{
    return $this->belongsTo(User::class, 'waiter_id');
}

public function user(): BelongsTo  // Alias de waiter
{
    return $this->belongsTo(User::class, 'waiter_id');
}

public function table(): BelongsTo
{
    return $this->belongsTo(Table::class);
}

public function orderProducts()
{
    return $this->hasMany(OrderProduct::class);
}
```

#### 4. Modelo OrderProduct
- `quantity`: cantidad del producto
- `notes`: notas adicionales (nullable)
- Relación `product`: `belongsTo(Product::class)`

---

## Formato de Salida Esperado

Para ambas implementaciones:
1. Código completo del Resource modificado
2. Código completo de la(s) vista(s) Blade
3. Método `getEloquentQuery()` con eager loading
4. Comandos de validación: `php -l` + `php artisan view:clear`

---

## Consideraciones Importantes

### Optimización
- **Siempre usar eager loading** para evitar N+1
- Cargar todas las relaciones necesarias en `getEloquentQuery()`

### Formato Español
- Moneda: `$ X.XXX,XX` (separador miles: punto, decimales: coma)
- Fechas: `d/m/Y H:i`

### Modo Oscuro
- Usar variantes `dark:` en todos los estilos Tailwind
- Colores: gray-50/900, gray-100/800, gray-200/700

### Badges y Estados
- Estados de pedido: en_proceso, servido, pagado, cancelado
- Colores semánticos: warning, success, info, danger, primary, gray

### Iconos
- Usar Heroicons disponibles en Filament
- Formato: `heroicon-o-nombre` (outline) o `heroicon-m-nombre` (mini)

---

## Verificación Final

Después de implementar, verificar:
- ✅ No hay consultas N+1 (revisar query log)
- ✅ Modo oscuro funciona correctamente
- ✅ Todos los textos están en español
- ✅ Formato de moneda y fechas es correcto
- ✅ Badges y colores son apropiados
- ✅ Vistas Blade manejan casos vacíos
- ✅ Sintaxis PHP sin errores

---

## Ejemplo de Uso

**Categorías:**
```
Hamburguesas (8 productos) → [Ver Productos]
  └─ Panel lateral:
       • Hamburguesa Simple - $5.000,00
       • Hamburguesa Completa - $7.500,00
       ...
```

**Pedidos:**
```
[▼] Pedido #42 | En Proceso | Mesa 5 | 3 productos | $ 18.500,00
    └─ Detalle expandido:
         Producto          | Cant. | P.Unit    | Subtotal
         ------------------------------------------------------
         Hamburguesa       | 2     | $7.500,00 | $15.000,00
         Coca Cola         | 1     | $3.500,00 | $ 3.500,00
         ------------------------------------------------------
         TOTAL DEL PEDIDO:                     | $18.500,00
```
