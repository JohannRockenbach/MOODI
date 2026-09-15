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
| Este archivo es el ÚNICO origen del schedule: NO registrar comandos en
| bootstrap/app.php (duplicaría la ejecución de cada tarea).
|
*/

// Anti-desperdicio: detectar ingredientes por vencer y sugerir recetas temporales.
Schedule::command('stock:check-expiry')->dailyAt('08:00')->withoutOverlapping();

// Promoción por clima: avisar a super_admin si el día es lluvioso.
Schedule::command('promo:check-weather')->dailyAt('09:00')->withoutOverlapping();

// Fidelización: cumpleaños de clientes.
Schedule::command('loyalty:check-promo')->dailyAt('08:30')->withoutOverlapping();

// Publicar/ocultar productos temporales según el estado de su lote crítico.
Schedule::command('products:sync-temporals')->hourly()->withoutOverlapping();

// Campañas programadas (status=scheduled) cuya fecha de envío ya venció.
// withoutOverlapping: evita doble envío de email si una corrida todavía no termina.
Schedule::command('campaign:send-scheduled')->everyFiveMinutes()->withoutOverlapping();