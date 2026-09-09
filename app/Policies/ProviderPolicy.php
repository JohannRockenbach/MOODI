<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Provider;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProviderPolicy
{
    use HandlesAuthorization;

    /**
     * Pre-authorization: solo 'super_admin' hace bypass de las comprobaciones.
     * Devolver null deja que los métodos por habilidad decidan (denegado por defecto).
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
    public function view(User $user, Provider $provider): bool
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
    public function update(User $user, Provider $provider): bool
    {
        return $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Provider $provider): bool
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
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Provider $provider): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Provider $provider): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Provider $provider): bool
    {
        return $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }
}