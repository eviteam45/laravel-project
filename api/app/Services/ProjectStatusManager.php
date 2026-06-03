<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Project workflow:
 *   draft → submitted → in_review → approved → installed → closed
 *   (+ rejected from in_review)
 */
class ProjectStatusManager extends StatusTransitionManager
{
    public function graph(): array
    {
        return [
            'draft' => ['submitted' => ['contractor']],
            'submitted' => ['in_review' => ['admin']],
            'in_review' => [
                'approved' => ['admin'],
                'rejected' => ['admin'],
            ],
            'approved' => ['installed' => ['contractor', 'admin']],
            'installed' => ['closed' => ['admin']],
            'rejected' => [],
            'closed' => [],
        ];
    }

    protected function ownerCheck(Model $subject, User $actor): bool
    {
        /** @var Project $subject */
        return ($actor->contractor && $subject->contractor_id === $actor->contractor->id)
            || ($actor->customer && $subject->customer_id === $actor->customer->id);
    }
}
