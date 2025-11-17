<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
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

    public function view(User $user, Sale $sale): bool
    {
        return $user->restaurant_id === $sale->restaurant_id || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin','cashier']);
    }

    public function update(User $user, Sale $sale): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $user->hasRole('admin');
    }
}
