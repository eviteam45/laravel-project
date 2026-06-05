<?php

namespace App\Services;

use App\Jobs\ProcessApplicationTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ApplicationStatusManager extends StatusTransitionManager
{
    public function graph(): array
    {
        return [
            'started' => [
                'in_progress' => ['contractor', 'customer'],
                'withdrawn' => ['customer', 'contractor'],
            ],
            'in_progress' => [
                'submitted' => ['contractor', 'customer'],
                'withdrawn' => ['customer', 'contractor'],
            ],
            'submitted' => [
                'under_review' => ['admin'],
                'withdrawn' => ['customer'],
            ],
            'under_review' => [
                'reserved' => ['admin'],
                'rejected' => ['admin'],
                'withdrawn' => ['customer'],
            ],
            'reserved' => [
                'paid' => ['admin'],
            ],
            'rejected' => [],
            'paid' => [],
            'withdrawn' => [],
        ];
    }

    protected function ownerCheck(Model $subject, User $actor): bool
    {

        $project = $subject->project;

        return ($actor->contractor && $project->contractor_id === $actor->contractor->id)
            || ($actor->customer && $project->customer_id === $actor->customer->id);
    }

    protected function mutations(Model $subject, string $to, array $context): array
    {

        if ($to === 'submitted') {
            if ($missing = $subject->missingStepKeys()) {
                throw ValidationException::withMessages([
                    'steps' => ['These steps are incomplete: '.implode(', ', $missing)],
                ]);
            }
            if ($subject->documents()->doesntExist()) {
                throw ValidationException::withMessages([
                    'documents' => ['At least one document is required before submitting.'],
                ]);
            }

            return [['submitted_at' => now(), 'current_step' => null], []];
        }

        if ($to === 'reserved') {
            $amount = $context['incentive_amount'] ?? null;
            if ($amount === null) {
                throw ValidationException::withMessages([
                    'incentive_amount' => ['An incentive amount is required to reserve funds.'],
                ]);
            }

            return [
                ['incentive_amount' => $amount],
                ['incentive_amount' => ['from' => $subject->incentive_amount, 'to' => $amount]],
            ];
        }

        return [[], []];
    }

    protected function sideEffects(Model $subject, string $from, string $to, User $actor, array $context): void
    {

        ProcessApplicationTransition::dispatch($subject->getKey(), $from, $to, $actor->id)->afterCommit();
    }
}
