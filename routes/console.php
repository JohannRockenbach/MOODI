<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas (procesos automatizados)
|--------------------------------------------------------------------------
|
| Los comandos del dominio se ejecutan solos. En producción hay que correr
| `php artisan schedule:work` o configurar el cron `* * * * * php artisan
| schedule:run` (ver README / deploy).
|
*/

// Anti-desperdicio: detectar ingredientes por vencer y sugerir recetas temporales.
Schedule::command('stock:check-expiry')->dailyAt('08:00');

// Promoción por clima: avisar a super_admin si el día es lluvioso.
Schedule::command('promo:check-weather')->dailyAt('09:00');

// Fidelización: cumpleaños de clientes.
Schedule::command('loyalty:check-promo')->dailyAt('10:00');

// Publicar/ocultar productos temporales según el estado de su lote crítico.
Schedule::command('products:sync-temporals')->hourly();

// Campañas programadas (status=scheduled) cuya fecha de envío ya venció.
Schedule::command('campaign:send-scheduled')->everyFiveMinutes();