<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function before(User $user)
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Salón: Mozo y super_admin gestionan pedidos. El Cocinero NO ve el
        // listado de pedidos (ni por URL): su mundo es Cocina + Recetas.
        // Cajero tampoco opera pedidos.
        return $user->hasAnyRole(['super_admin', 'Mozo']);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id || $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return $user->exists();
    }

    public function update(User $user, Order $order): bool
    {
        // Allow waiter that created the order or admins
        return $user->id === $order->waiter_id || $user->hasRole('super_admin');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasRole('super_admin');
    }
}
