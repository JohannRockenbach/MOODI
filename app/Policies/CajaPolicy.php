<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Caja;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class CajaPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): bool|null
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_caja');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Caja $caja): bool
    {
        return $user->can('view_caja');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_caja');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Caja $caja): bool
    {
        return $user->can('update_caja');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Caja $caja): bool
    {
        return $user->can('delete_caja');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_caja');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Caja $caja): bool
    {
        return $user->can('{{ ForceDelete }}');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('{{ ForceDeleteAny }}');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Caja $caja): bool
    {
        return $user->can('{{ Restore }}');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('{{ RestoreAny }}');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Caja $caja): bool
    {
        return $user->can('{{ Replicate }}');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('{{ Reorder }}');
    }

    /**
     * Determine whether the user can open the model.
     */
    public function open(User $user, Caja $caja): bool
    {
        return $user->can('open_caja');
    }

    /**
     * Determine whether the user can close the model.
     */
    public function close(User $user, Caja $caja): bool
    {
        return $user->can('close_caja');
    }
}
