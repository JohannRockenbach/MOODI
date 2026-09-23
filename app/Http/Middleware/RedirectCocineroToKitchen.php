<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectCocineroToKitchen
{
    /**
     * El Cocinero entra directo a la pantalla de cocina (KDS), no al dashboard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (Auth::check() && $user && $user->hasRole('Cocinero')) {
            if ($request->is('admin') || $request->is('admin/') || $request->is('admin/dashboard')) {
                return redirect(route('filament.admin.resources.orders.kitchen'));
            }
        }

        return $next($request);
    }
}