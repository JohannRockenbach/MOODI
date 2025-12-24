<?php

namespace App\Http\Middleware;

use App\Filament\Pages\TableMap;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectMozoToTableMap
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Si el usuario está autenticado y tiene el rol "Mozo"
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        if (Auth::check() && $user && $user->hasRole('Mozo')) {
            // Si está intentando acceder al dashboard, redirigir al mapa de mesas
            if ($request->is('admin') || $request->is('admin/') || $request->is('admin/dashboard')) {
                return redirect(TableMap::getUrl());
            }
        }

        return $next($request);
    }
}
