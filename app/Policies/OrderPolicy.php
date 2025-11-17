<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function before(User $user)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return $user->exists();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->exists();
    }

    public function update(User $user, Order $order): bool
    {
        // Allow waiter that created the order or admins
        return $user->id === $order->waiter_id || $user->hasRole('admin');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }
}
