# 🚀 Sistema de Automatización de Marketing MOODI

## 📋 Resumen de las 3 Automatizaciones Implementadas

### 1. 🌦️ **Automatización por Clima** (`CheckWeatherPromo`)
**Comando:** `php artisan promo:check-weather`

**Estrategias:**
- **Escenario A - Lluvia 🌧️**: Combo Netflix (Hamburguesa intermedia + Bebida)
- **Escenario B - Calor >28°C ☀️**: Combo After Office (Cerveza + Papas)
- **Escenario C - Clima Estándar 🌤️**: Menú Ejecutivo (Hamburguesa + Papas)

**Tecnología:**
- API Open-Meteo (clima de Apóstoles, Misiones)
- Análisis de stock inteligente
- Selección de productos con precios intermedios

**Salida:**
- Notificación Filament con:
  - Botón "Crear Campaña" → Pre-llena `SendCampaign`
  - Botón "Ver Productos" → Lista de productos

---

### 2. ♻️ **Automatización Anti-Desperdicio** (`CheckStockExpiry`)
**Comando:** `php artisan stock:check-expiry`

**Estrategia:**
- Detecta ingredientes que vencen en ≤3 días
- Excluye insumos base (Harina, Levadura, Sal, etc.)
- Agrupa por ingrediente crítico (mayor cantidad)
- Busca productos que usen ese ingrediente
- **Prioriza Hamburguesas** sobre otros productos

**Lógica Inteligente:**
```php
$ignoredIngredients = ['Harina', 'Levadura', 'Sal', 'Azúcar', 
                       'Agua', 'Aceite', 'Papas Congeladas'];
```

**Salida:**
- Notificación Filament con:
  - Ingrediente crítico + cantidad + días hasta vencer
  - Producto recomendado para promocionar
  - Botones: "Crear Campaña" + "Ver Ingrediente"

**Ejemplo Real:**
- Detectó: 267 unidades de Queso Cheddar (vence en ~1 día)
- Recomendó: Bacon Cheeseburger (hamburguesa prioritaria)

---

### 3. 👑 **Automatización de Fidelización** (`CheckLoyaltyPromo`)
**Comando:** `php artisan loyalty:check-promo`

**Estrategias:**

#### **A) Cumpleaños 🎂**
```php
whereMonth('birthday', now()->month)
->whereDay('birthday', now()->day)
```
- Detecta cumpleaños del día
- Calcula edad automáticamente
- Sugiere: Postre gratis o descuento 15%
- Pre-llena email del cumpleañero

#### **B) Clientes VIP 👑**
```php
whereHas('orders', fn($q) => $q->where('created_at', '>=', now()->subDays(30)), '>=', 5)
```
- Detecta clientes con 5+ pedidos en 30 días
- Muestra cantidad de pedidos
- Sugiere: Cupón de fidelidad 20% (código VIP20)
- Beneficios: Prioridad cocina, postre gratis, promos exclusivas

**Salida:**
- Notificaciones Filament separadas para cada cliente:
  - **Cumpleaños**: Icono 🎂 (verde), email pre-llenado
  - **VIP**: Icono ⭐ (amarillo), estadísticas de pedidos
  - Botones: "Crear Campaña" + "Ver Cliente" + "Ver Pedidos" (VIP)

**Prueba Real:**
- ✅ 2 cumpleaños detectados (Juan Cumpleañero, María VIP)
- ✅ 1 cliente VIP detectado (María VIP con 6 pedidos)
- ✅ 3 notificaciones enviadas a administradores

---

## 🛠️ Integración con Sistema de Emails

Todas las automatizaciones se integran con:
- **`SendCampaign` Page**: Formulario para crear/probar campañas
- **`PromoEmail` Mailable**: Template profesional de emails
- **Pre-llenado inteligente**: Subject, Body, Email Test
- **Livewire Form**: Validación y envío en tiempo real

---

## 📅 Programación Automática

Para ejecutar automáticamente, añadir al `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Clima: Cada mañana 8am y tarde 6pm
    $schedule->command('promo:check-weather')->dailyAt('08:00');
    $schedule->command('promo:check-weather')->dailyAt('18:00');
    
    // Anti-Desperdicio: Diario 7am
    $schedule->command('stock:check-expiry')->dailyAt('07:00');
    
    // Fidelización: Diario 9am
    $schedule->command('loyalty:check-promo')->dailyAt('09:00');
}
```

---

## 🎯 Estadísticas de Implementación

| Automatización | Líneas Código | Escenarios | Notificaciones |
|----------------|---------------|------------|----------------|
| Clima          | ~200          | 3          | 1 por análisis |
| Anti-Desperdicio | ~250        | 1          | 1 por ingrediente crítico |
| Fidelización   | ~280          | 2          | N por cliente detectado |
| **TOTAL**      | **~730**      | **6**      | **Variable** |

---

## 🚀 Flujo Completo de Marketing

```
┌─────────────────────────────────────────────────────────────┐
│ SISTEMA DE AUTOMATIZACIÓN DE MARKETING MOODI               │
└─────────────────────────────────────────────────────────────┘
                           │
           ┌───────────────┼───────────────┐
           ▼               ▼               ▼
    [CLIMA 🌦️]    [DESPERDICIO ♻️]   [FIDELIZACIÓN 👑]
           │               │               │
           │               │               │
           ▼               ▼               ▼
    Notificación    Notificación    Notificación(es)
     Filament        Filament         Filament
           │               │               │
           └───────────────┼───────────────┘
                           ▼
                  [Admin Dashboard 📊]
                           │
                           ▼
                  "Crear Campaña" 🎯
                           │
                           ▼
                  [SendCampaign Page 📧]
                  - Subject pre-llenado
                  - Body pre-llenado
                  - Email Test pre-llenado
                           │
                           ▼
                  [PromoEmail 💌]
                  Template profesional
                           │
                           ▼
                  [Cliente Final 🎁]
                  Email recibido
```

---

## 📊 Métricas Esperadas

### Clima
- **Conversión**: +15-25% en días de lluvia/calor
- **Ticket promedio**: +$500 por combos sugeridos

### Anti-Desperdicio
- **Reducción de pérdidas**: 30-40%
- **ROI**: Ingredientes aprovechados antes de vencer

### Fidelización
- **Retención cumpleaños**: +60% retorno en 30 días
- **Clientes VIP**: 80% de ingresos recurrentes

---

## 🔧 Comandos de Gestión

```bash
# Ejecutar manualmente
php artisan promo:check-weather
php artisan stock:check-expiry
php artisan loyalty:check-promo

# Ver todas las notificaciones
# → Dashboard Admin → Bell Icon 🔔

# Crear datos de prueba
php scripts/test_loyalty_system.php

# Limpiar caché
php artisan cache:clear
php artisan view:clear
```

---

## ✅ Estado del Proyecto

- [x] WeatherService + WeatherOverview Widget
- [x] CheckWeatherPromo (3 escenarios)
- [x] CheckStockExpiry (con exclusiones inteligentes)
- [x] CheckLoyaltyPromo (cumpleaños + VIP)
- [x] SendCampaign Page (Filament)
- [x] PromoEmail Mailable
- [x] Integración Order ↔ Cliente (nullable)
- [x] Scripts de prueba
- [ ] Scheduler automático (opcional)
- [ ] Dashboard de métricas (opcional)

---

## 🎓 Arquitectura del Sistema

**Patrón: Command Pattern + Observer Pattern + Strategy Pattern**

```
app/
├── Console/Commands/
│   ├── CheckWeatherPromo.php      # Estrategia Clima
│   ├── CheckStockExpiry.php       # Estrategia Anti-Desperdicio
│   └── CheckLoyaltyPromo.php      # Estrategia Fidelización
├── Services/
│   └── WeatherService.php         # API Open-Meteo
├── Filament/
│   ├── Pages/
│   │   └── SendCampaign.php       # UI Campañas
│   └── Widgets/
│       └── WeatherOverview.php    # Dashboard Clima
├── Mail/
│   └── PromoEmail.php             # Template Email
└── Models/
    ├── Order.php                   # Relación con Cliente
    ├── Cliente.php                 # Relación con Order
    ├── Ingredient.php
    ├── IngredientBatch.php
    ├── Product.php
    └── Recipe.php
```

---

**Desarrollado para:** MOODI - Sistema de Gestión de Restaurantes  
**Tecnologías:** Laravel 12.35.1, Filament v3, PostgreSQL, Open-Meteo API  
**Autor:** Sistema de Automatización de Marketing Inteligente  
**Fecha:** Noviembre 2025
