<?php

namespace App\Policies;

use App\Models\Contractor;
use App\Models\User;

/**
 * Contractor records are managed by admins only.
 */
class ContractorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Contractor $contractor): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Contractor $contractor): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Contractor $contractor): bool
    {
        return $user->isAdmin();
    }
}
