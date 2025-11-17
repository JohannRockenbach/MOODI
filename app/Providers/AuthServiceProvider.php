<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Sale;
use App\Policies\OrderPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\SalePolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
        Reservation::class => ReservationPolicy::class,
        Sale::class => SalePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Simple Gate: admin role bypass (if Spatie present this will call hasRole())
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }
        });
    }
}
