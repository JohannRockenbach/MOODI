<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
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

    public function view(User $user, Reservation $reservation): bool
    {
        return $user->restaurant_id === $reservation->restaurant_id || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->exists();
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $user->hasRole('admin') || $user->id === $reservation->customer_id;
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->hasRole('admin');
    }
}
