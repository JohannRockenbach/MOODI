<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Table;
use Illuminate\Auth\Access\HandlesAuthorization;

class TablePolicy
{
    use HandlesAuthorization;

    /**
     * Pre-authorization: only 'super_admin' bypasses the per-ability checks.
     * Returning null lets the ability methods below decide (all deny by default).
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Table $table): bool
    {
        return $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Table $table): bool
    {
        return $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Table $table): bool
    {
        return $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }

    /**
     * Las mesas NO usan soft deletes: restore/forceDelete no aplican y se deniegan.
     */

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Table $table): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Table $table): bool
    {
        return false;
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Table $table): bool
    {
        return false;
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return false;
    }
}