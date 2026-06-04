<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->ownsAsContractor($user, $project)
            || $this->ownsAsCustomer($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->isContractor();
    }

    public function update(User $user, Project $project): bool
    {
        return $this->ownsAsContractor($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->ownsAsContractor($user, $project);
    }

    protected function ownsAsContractor(User $user, Project $project): bool
    {
        return $user->contractor !== null
            && $project->contractor_id === $user->contractor->id;
    }

    protected function ownsAsCustomer(User $user, Project $project): bool
    {
        return $user->customer !== null
            && $project->customer_id === $user->customer->id;
    }
}
