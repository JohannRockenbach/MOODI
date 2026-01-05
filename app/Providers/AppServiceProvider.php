<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Http\Livewire\CajaBalance;
use Illuminate\Support\Facades\URL;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Livewire component alias for CajaBalance (ensures @livewire('caja-balance') resolves)
        if (class_exists(CajaBalance::class)) {
            Livewire::component('caja-balance', CajaBalance::class);
        }
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
