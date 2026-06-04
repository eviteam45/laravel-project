<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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

        return ($actor->contractor && $subject->contractor_id === $actor->contractor->id)
            || ($actor->customer && $subject->customer_id === $actor->customer->id);
    }

    protected function sideEffects(Model $subject, string $from, string $to, User $actor, array $context): void
    {
        if ($subject instanceof Project) {
            app(TransitionNotifier::class)->notifyProjectTransition($subject, $from, $to, $actor->id);
        }
    }
}
