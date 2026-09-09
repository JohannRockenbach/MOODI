<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('promo:check-weather')->dailyAt('09:00');
        $schedule->command('stock:check-expiry')->dailyAt('08:00');
        $schedule->command('loyalty:check-promo')->dailyAt('08:30');
        $schedule->command('campaign:send-scheduled')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
