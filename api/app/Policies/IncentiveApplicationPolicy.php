<?php

namespace App\Policies;

use App\Models\IncentiveApplication;
use App\Models\Project;
use App\Models\User;

class IncentiveApplicationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true; // scoped by IncentiveApplication::visibleTo()
    }

    public function view(User $user, IncentiveApplication $application): bool
    {
        return $this->canAccessProject($user, $application->project);
    }

    /**
     * The project's contractor or customer (or admin) may create its application.
     */
    public function create(User $user, Project $project): bool
    {
        return $this->canAccessProject($user, $project);
    }

    /**
     * Both the contractor and the customer tied to the project may complete
     * the multi-step form (§1: applicants fill it; contractors assist).
     */
    public function update(User $user, IncentiveApplication $application): bool
    {
        return $this->canAccessProject($user, $application->project);
    }

    /**
     * Deleting a draft application is limited to the contractor (or admin).
     */
    public function delete(User $user, IncentiveApplication $application): bool
    {
        return $user->contractor !== null
            && $application->project->contractor_id === $user->contractor->id;
    }

    protected function canAccessProject(User $user, Project $project): bool
    {
        return ($user->contractor !== null && $project->contractor_id === $user->contractor->id)
            || ($user->customer !== null && $project->customer_id === $user->customer->id);
    }
}
