<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function before(User $user)
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Ventas: Cajero y super_admin. Mozo/Cocinero no ven el listado de ventas.
        return $user->hasAnyRole(['super_admin', 'Cajero']);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->restaurant_id === $sale->restaurant_id || $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Cajero']);
    }

    public function update(User $user, Sale $sale): bool
    {
        if ($sale->status === 'annulled' || ! is_null($sale->annulled_at)) {
            return false;
        }

        return $user->hasRole('super_admin');
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Permiso de anulación (Sprint 1):
     * - Si existe permiso granular `update_sale`, se usa como permiso más cercano.
     * - Fallback legacy: rol admin.
     */
    public function annul(User $user, Sale $sale): bool
    {
        return $user->hasRole('super_admin');
    }

    public function restoreAnnulled(User $user, Sale $sale): bool
    {
        return $user->hasRole('super_admin');
    }
}
