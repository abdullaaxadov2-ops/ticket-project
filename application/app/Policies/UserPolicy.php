<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function manage(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

}
