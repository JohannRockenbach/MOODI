# MOODI - Sistema de Gestión para Restaurantes

## 📋 Resumen Ejecutivo

**MOODI** es un sistema integral de gestión para restaurantes desarrollado con **Laravel 12**, **Filament 3** (panel administrativo), **Livewire** (componentes dinámicos) y **PostgreSQL** (base de datos). El sistema permite administrar todas las operaciones del negocio: desde el control de inventario y proveedores, hasta la gestión de mesas, pedidos, reservas, ventas y cajas.

---

## 🏗️ Arquitectura Técnica

### Stack Tecnológico
- **Backend**: Laravel 12 (PHP 8.3.6)
- **Admin UI**: Filament 3 (Resources, Forms, Tables, RelationManagers)
- **Frontend**: Livewire + Blade + TailwindCSS
- **Base de Datos**: PostgreSQL
- **Autenticación y Permisos**: Laravel Breeze + Spatie Laravel Permission + Filament Shield
- **Iconos**: Blade UI Icons (Heroicons)

### Patrón de Arquitectura
- **MVC** (Model-View-Controller) de Laravel
- **Policy-based Authorization** (Spatie + Filament Shield)
- **Soft Deletes** para eliminaciones lógicas
- **Eloquent ORM** para manejo de relaciones
- **Seeders idempotentes** para datos de prueba

---

## 📦 Módulos del Sistema

### 1. **Administración y Configuración**
Gestión de la información base del restaurante y usuarios.

#### Modelos:
- **Restaurant**: Representa un restaurante (nombre, dirección, CUIT, horarios, teléfono).
- **User**: Usuarios del sistema (empleados, mozos, administradores).
  - Integrado con **Spatie Roles & Permissions**.
  - Soft deletes habilitado.
  - Relación con `Restaurant` (un usuario pertenece a un restaurante).

#### Recursos Filament:
- **RestaurantResource**: CRUD de restaurantes (grupo "Administración", navigationSort=0).
- **UserResource**: CRUD de usuarios y asignación de roles (grupo "Administración", navigationSort=0).
- **RoleResource** (Filament Shield): Gestión de roles y permisos (ahora en grupo "Administración").

---

### 2. **Inventario y Productos**
Control de ingredientes, proveedores, recetas y productos del menú.

#### Modelos:
- **Provider**: Proveedores de ingredientes (nombre, CUIT, contacto).
- **Ingredient**: Ingredientes del inventario (nombre, unidad de medida, stock mínimo/actual, precio unitario).
  - Soft deletes habilitado.
  - Relación many-to-many con `Provider` (tabla pivot `ingredient_provider`).
- **Recipe**: Recetas para preparar productos (nombre, instrucciones, tiempo de preparación).
  - Relación many-to-many con `Ingredient` (tabla pivot `ingredient_recipe` con campo `quantity`).
- **Product**: Productos del menú (nombre, descripción, precio, categoría, stock).
  - Soft deletes habilitado.
  - Relación con `Recipe` (un producto puede tener una receta asociada).
  - Relación con `Category`.

#### Recursos Filament:
- **ProviderResource**: CRUD de proveedores (grupo "Inventario y Productos").
- **IngredientResource**: CRUD de ingredientes (grupo "Inventario y Productos").
- **RecipeResource**: CRUD de recetas (grupo "Inventario y Productos").
- **ProductResource**: CRUD de productos con gestión de stock (grupo "Inventario y Productos").

---

### 3. **Categorías de Productos (Jerárquicas)**
Organización de productos en categorías y subcategorías.

#### Modelos:
- **Category**: Categorías y subcategorías de productos (con jerarquía padre-hijo).
  - Campo `parent_id` (self-referencing).
  - Relación `parent()` y `children()`.
  - Relación `products()` (una categoría tiene muchos productos).

#### Recursos Filament:
- **CategoryResource**: CRUD de categorías con soporte de jerarquía (grupo "Inventario y Productos").
  - **Columna especial**: Panel expandible Livewire (`CategoryProductsPanel`) que muestra productos por categoría en una subtabla inline.
  - **Validación**: Confirmación antes de eliminar una categoría (reasignar productos o prevenir borrado si tiene productos asociados).

#### Componente Livewire:
- **CategoryProductsPanel**: Componente que muestra una lista de productos pertenecientes a una categoría dentro de la tabla de Filament (expansión inline).

---

### 4. **Operaciones del Salón**
Gestión de mesas, reservas y pedidos.

#### Modelos:
- **Table**: Mesas del restaurante (número, capacidad, ubicación, estado, mozo asignado).
  - Relación con `User` (mozo asignado).
  - Relación con `Reservation` y `Order`.
- **Reservation**: Reservas de mesas (fecha/hora, cliente, número de personas, estado).
  - Relación con `Table` y `User` (cliente).
- **Order**: Pedidos realizados en una mesa (fecha, estado, total, observaciones).
  - Relación con `Table`, `User` (mozo), `Restaurant`.
  - Relación many-to-many con `Product` a través de `OrderProduct` (tabla pivot con `quantity` y `price`).
- **OrderProduct**: Tabla pivot para los productos de un pedido (cantidad, precio unitario).

#### Recursos Filament:
- **TableResource**: CRUD de mesas (grupo "Operaciones del Salón").
- **ReservationResource**: CRUD de reservas (grupo "Operaciones del Salón", navigationSort=1, etiquetas en español).
- **OrderResource**: CRUD de pedidos (grupo "Operaciones del Salón", navigationSort=2, icono 'heroicon-o-rectangle-stack', etiquetas en español).

---

### 5. **Ventas y Finanzas**
Registro de ventas, descuentos, facturación y gestión de cajas.

#### Modelos:
- **Sale**: Ventas realizadas (fecha, monto total, método de pago, estado).
  - Relación con `Order` (una venta se genera a partir de un pedido).
  - Relación con `Caja` (una venta pertenece a una caja).
  - Relación many-to-many con `Discount` (tabla pivot `discount_sale` con campo `amount_discounted`).
- **Discount**: Descuentos aplicables (código, descripción, tipo, valor).
  - Relación con `Restaurant`.
  - Relación many-to-many con `Sale`.
- **Invoice**: Facturas emitidas (número CAE, datos del cliente en JSON).
  - Relación one-to-one con `Sale`.
- **Caja**: Registro de apertura/cierre de caja (fecha apertura/cierre, saldo inicial/final, total ventas, estado).
  - Relación con `Restaurant`.
  - Relación con `User` (usuario que abrió/cerró).
  - Relación con `Sale` (una caja tiene muchas ventas).
  - **Validación**: Saldos no negativos y dentro de rango (0 - 99,999,999.99).

#### Recursos Filament:
- **SaleResource**: CRUD de ventas (grupo "Ventas y Finanzas", icono 'heroicon-o-currency-dollar').
- **DiscountResource**: CRUD de descuentos (grupo "Ventas y Finanzas").
- **CajaResource**: CRUD de cajas (grupo "Ventas y Finanzas").

---

### 6. **Compras a Proveedores**
Gestión de órdenes de compra de ingredientes.

#### Modelos:
- **PurchaseOrder**: Órdenes de compra a proveedores (fecha, total, estado).
  - Relación con `Provider` y `Restaurant`.
- **PurchaseOrderDetail**: Detalle de cada orden de compra (ingrediente, cantidad, precio unitario).
  - Relación con `PurchaseOrder` e `Ingredient`.

#### Recursos Filament:
- **(Pendiente confirmar si existe recurso Filament para PurchaseOrder)**

---

## 🔐 Autenticación y Permisos

### Sistema de Roles y Permisos
- **Spatie Laravel Permission**: Gestión de roles y permisos a nivel de modelo.
- **Filament Shield**: Integración con Filament para generar automáticamente permisos por recurso/página/widget.
  - **RoleResource**: Recurso de administración de roles (movido al grupo "Administración").
  - **Políticas (Policies)**: Cada modelo tiene su Policy (CajaPolicy, CategoryPolicy, OrderPolicy, etc.).
  - **Super Admin**: Rol `super_admin` con acceso completo.
  - **Panel User**: Rol `panel_user` para usuarios básicos del panel.

### Configuración Shield (`config/filament-shield.php`):
- Navigation registrada para Roles con sort=0 (junto a Dashboard).
- Traducción al español (`resources/lang/vendor/filament-shield/es/filament-shield.php`): grupo "Administración".

---

## 🌐 Navegación del Panel Filament

### Grupos de Navegación (en español):
1. **Escritorio** (Dashboard) - navigationSort = -10
2. **Administración** (navigationSort = 0)
   - Restaurantes
   - Usuarios
   - Roles (Filament Shield)
3. **Inventario y Productos**
   - Categorías
   - Productos
   - Ingredientes
   - Recetas
   - Proveedores
4. **Operaciones del Salón**
   - Mesas
   - Reservas (navigationSort = 1)
   - Pedidos (navigationSort = 2)
5. **Ventas y Finanzas**
   - Ventas
   - Descuentos
   - Cajas

---

## 🗃️ Base de Datos

### Migraciones Principales (en orden cronológico):
1. `create_users_table` (usuarios base Laravel)
2. `create_restaurants_table` (restaurantes)
3. `create_categories_table` + `add_hierarchy_to_categories` (categorías jerárquicas)
4. `create_products_table` + `add_stock_to_products_table` + `add_recipe_id_to_products_table`
5. `create_providers_table` (proveedores)
6. `create_ingredients_table` (ingredientes)
7. `ingredient_provider_table` (pivot)
8. `create_recipes_table` (recetas)
9. `ingredient_recipe_table` (pivot)
10. `create_tables_table` (mesas)
11. `create_reservations_table` (reservas)
12. `create_orders_table` (pedidos)
13. `order_product_table` (pivot con quantity y price)
14. `create_purchase_orders_table` (órdenes de compra)
15. `create_purchase_order_details_table` (detalle de órdenes de compra)
16. `create_discounts_table` (descuentos)
17. `create_sales_table` (ventas)
18. `discount_sale_table` (pivot con amount_discounted)
19. `create_invoices_table` (facturas)
20. `create_cajas_table` + `add_caja_id_to_sales_table` + `add_description_to_cajas_table`
21. `create_permission_tables` (Spatie Permission)
22. Soft deletes en Users, Products, Ingredients, Restaurants

### Restricciones y Validaciones:
- **UNIQUE**: `categories.name`, `providers.cuit`, etc.
- **Soft Deletes**: Users, Products, Ingredients, Restaurants.
- **Foreign Keys**: Todas las relaciones con `onDelete('cascade')` o `onDelete('restrict')` según el caso.
- **Decimals**: Precios, saldos y montos con `decimal(10,2)`.

---

## 📊 Seeders y Factories

### Seeders Implementados:
- **CategorySeeder** (idempotente):
  - Crea categorías principales (Bebidas, Comidas, Postres, Entradas) y subcategorías.
  - Evita duplicados usando `firstOrCreate` y lógica para nombres reutilizados.
- **OrderReservationSaleSeeder** (idempotente):
  - Crea una reserva, pedido asociado con productos, y venta asociada con descuento.
  - Usa `firstOrCreate`/`updateOrCreate` para no duplicar datos.

### Factories Pendientes:
- **OrderFactory**
- **OrderProductFactory**
- **ReservationFactory**
- **SaleFactory**

---

## 🎨 UI y Componentes Personalizados

### Livewire Components:
- **CategoryProductsPanel**: Panel expandible inline en la tabla de categorías que muestra los productos asociados.
  - Vista: `resources/views/livewire/category-products-panel.blade.php`
  - Permite ver y editar productos desde la misma fila de la categoría.

### ViewColumns Personalizadas:
- **CategoryResource**: Columna "Productos" que monta el componente Livewire para expansión inline.

### Iconos:
- **Heroicons** (Blade UI Icons): Todos los iconos usados son del set Heroicons para evitar errores `SvgNotFound`.
- **Correcciones aplicadas**:
  - `OrderResource`: icono cambiado a `heroicon-o-rectangle-stack`.
  - `SaleResource`: icono cambiado a `heroicon-o-currency-dollar`.

---

## 🚀 Características Clave

### ✅ Implementadas:
1. **Gestión completa de restaurantes**: Multi-restaurante con relaciones.
2. **Categorías jerárquicas**: Subcategorías con UI expandible para productos.
3. **Control de inventario**: Ingredientes, recetas, stock de productos.
4. **Gestión de proveedores**: Registro y relación con ingredientes.
5. **Operaciones del salón**: Mesas, reservas, pedidos con productos.
6. **Ventas y finanzas**: Registro de ventas, descuentos aplicados, facturación.
7. **Cajas**: Apertura/cierre de caja con validación de saldos.
8. **Roles y permisos**: Filament Shield integrado con Spatie.
9. **Soft Deletes**: Eliminación lógica en usuarios, productos, ingredientes, restaurantes.
10. **Seeders idempotentes**: Datos de prueba sin duplicados.
11. **UI en español**: Navegación y etiquetas traducidas.

### 🔄 Parcialmente Implementadas:
1. **Factories**: Seeders funcionan pero factories para Order/Reservation/Sale están pendientes.
2. **RelationManagers adicionales**: Algunas relaciones (ej. pagos, descuentos en Sale) pueden beneficiarse de RelationManagers dedicados.

### 📝 Pendientes o Mejoras Futuras:
1. **Reportes y estadísticas**: Dashboard con gráficos de ventas, productos más vendidos, etc.
2. **Notificaciones**: Alertas de stock bajo, reservas próximas, etc.
3. **Integración con AFIP**: Facturación electrónica automática.
4. **App móvil o PWA**: Para que mozos tomen pedidos desde tablets/móviles.
5. **Sistema de turnos**: Gestión de turnos de empleados.
6. **Gestión de pagos**: Múltiples métodos de pago, pagos parciales, propinas.

---

## 🧪 Testing y Validación

### Comprobaciones Realizadas:
- ✅ **Sintaxis PHP**: `php -l` ejecutado en todos los archivos modificados (0 errores).
- ✅ **Migraciones**: `php artisan migrate` ejecutado (sin migraciones pendientes).
- ✅ **Seeders**: `CategorySeeder` y `OrderReservationSaleSeeder` ejecutados con éxito.
- ✅ **Cache limpiado**: `php artisan view:clear` y `php artisan cache:clear` ejecutados.

### Testing Pendiente:
- **PHPUnit**: Tests unitarios y de feature para modelos y controladores.
- **Pest**: Framework de testing instalado pero sin tests escritos aún.

---

## 📂 Estructura de Archivos Clave

```
MOODI/
├── app/
│   ├── Filament/
│   │   └── Resources/
│   │       ├── CategoryResource.php (con ViewColumn para productos)
│   │       ├── OrderResource.php (Operaciones del Salón)
│   │       ├── ReservationResource.php (Operaciones del Salón)
│   │       ├── SaleResource.php (Ventas y Finanzas)
│   │       ├── RestaurantResource.php (Administración)
│   │       ├── UserResource.php (Administración)
│   │       └── ... (otros recursos)
│   ├── Http/
│   │   └── Livewire/
│   │       └── CategoryProductsPanel.php (panel productos por categoría)
│   ├── Models/
│   │   ├── Restaurant.php
│   │   ├── User.php
│   │   ├── Category.php
│   │   ├── Product.php
│   │   ├── Ingredient.php
│   │   ├── Recipe.php
│   │   ├── Provider.php
│   │   ├── Table.php
│   │   ├── Reservation.php
│   │   ├── Order.php
│   │   ├── OrderProduct.php
│   │   ├── Sale.php
│   │   ├── Discount.php
│   │   ├── Invoice.php
│   │   ├── Caja.php
│   │   ├── PurchaseOrder.php
│   │   └── PurchaseOrderDetail.php
│   └── Policies/
│       ├── CajaPolicy.php
│       ├── CategoryPolicy.php
│       ├── OrderPolicy.php
│       └── ... (otras policies)
├── config/
│   └── filament-shield.php (config para Filament Shield)
├── database/
│   ├── migrations/ (33 migraciones)
│   ├── seeders/
│   │   ├── CategorySeeder.php (idempotente)
│   │   └── OrderReservationSaleSeeder.php (idempotente)
│   └── factories/ (pendientes para Order/Reservation/Sale)
├── resources/
│   ├── lang/
│   │   └── vendor/
│   │       └── filament-shield/
│   │           └── es/
│   │               └── filament-shield.php (traducción a español)
│   └── views/
│       └── livewire/
│           └── category-products-panel.blade.php
├── routes/
│   ├── web.php
│   └── auth.php
├── composer.json (Laravel 12, Filament 3, Spatie Permission, etc.)
├── package.json (TailwindCSS, Vite)
└── README.md (Laravel por defecto)
```

---

## 🛠️ Comandos Útiles

### Servidor de Desarrollo
```bash
php artisan serve
# http://localhost:8000
```

### Migraciones
```bash
php artisan migrate
php artisan migrate:fresh --seed  # Recrear DB + seeders
```

### Seeders
```bash
php artisan db:seed --class=CategorySeeder
php artisan db:seed --class=OrderReservationSaleSeeder
```

### Limpiar Caché
```bash
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Filament Shield
```bash
php artisan shield:generate          # Generar permisos
php artisan shield:install           # Instalar Shield
php artisan shield:publish           # Publicar RoleResource
```

### Testing
```bash
php artisan test                     # PHPUnit/Pest
```

---

## 👥 Roles del Sistema

### Roles Predefinidos (Spatie + Shield):
1. **super_admin**: Acceso completo al sistema.
2. **panel_user**: Usuario básico del panel.
3. **(Otros roles personalizados se pueden crear desde el RoleResource)**

### Permisos Generados Automáticamente:
- `view_any_category`, `view_category`, `create_category`, `update_category`, `delete_category`, etc.
- Permisos para todos los recursos: Category, Product, Ingredient, Recipe, Provider, Table, Reservation, Order, Sale, Discount, Caja, Restaurant, User.

---

## 📧 Contacto y Mantenimiento

**Desarrollador**: Johann Rockenbach  
**Repositorio**: JohannRockenbach/MOODI  
**Branch**: main  
**Fecha de Última Actualización**: 29 de octubre de 2025

---

## 📝 Notas Finales

Este sistema está diseñado para ser **modular** y **escalable**. Cada módulo (Inventario, Ventas, Operaciones) puede ser extendido o personalizado según las necesidades del restaurante. La arquitectura basada en **Filament 3** permite añadir nuevos recursos y relaciones de forma rápida y con una UI profesional lista para usar.

**MOODI** es ideal para:
- Restaurantes pequeños/medianos que necesitan centralizar su gestión.
- Cadenas de restaurantes con soporte multi-restaurante.
- Negocios que requieren control estricto de inventario y costos.
- Equipos que valoran una UI administrativa moderna y eficiente.

---

**¡Gracias por usar MOODI!** 🍽️✨
