<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generic, server-side-enforced status state machine.
 *
 * Subclasses declare a transition graph of `from => [to => [allowed roles]]`.
 *  - An edge that is not in the graph is rejected with a 422 (illegal transition).
 *  - A valid edge attempted by a role/owner that is not permitted is a 403.
 * Admins may perform any defined edge.
 */
abstract class StatusTransitionManager
{
    /**
     * @return array<string, array<string, list<string>>>
     */
    abstract public function graph(): array;

    /**
     * Whether a non-admin actor is connected to this subject (ownership).
     */
    abstract protected function ownerCheck(Model $subject, User $actor): bool;

    /**
     * Extra attributes to persist and change-log entries for a transition.
     * May throw ValidationException for transition-specific requirements.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function mutations(Model $subject, string $to, array $context): array
    {
        return [[], []];
    }

    /**
     * Fire-and-forget work after the status has changed (jobs, etc).
     */
    protected function sideEffects(Model $subject, string $from, string $to, array $context): void
    {
        //
    }

    public function canTransition(string $from, string $to): bool
    {
        return array_key_exists($to, $this->graph()[$from] ?? []);
    }

    /**
     * @return list<string> destinations reachable by this actor from $from
     */
    public function allowedFor(string $from, User $actor): array
    {
        $edges = $this->graph()[$from] ?? [];

        if ($actor->isAdmin()) {
            return array_keys($edges);
        }

        return array_keys(array_filter($edges, fn (array $roles) => in_array($actor->role, $roles, true)));
    }

    public function transition(Model $subject, string $to, User $actor, array $context = []): Model
    {
        $from = $subject->status;

        if (! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'to' => ["Illegal transition: '{$from}' → '{$to}'."],
            ]);
        }

        if (! $actor->isAdmin()) {
            $roles = $this->graph()[$from][$to] ?? [];

            if (! in_array($actor->role, $roles, true) || ! $this->ownerCheck($subject, $actor)) {
                throw new AuthorizationException('Your role is not permitted to make this transition.');
            }
        }

        // Validation/requirements run before any write (may throw 422).
        [$attributes, $changes] = $this->mutations($subject, $to, $context);

        // The status change and its audit entry must commit together.
        DB::transaction(function () use ($subject, $to, $attributes, $changes, $from, $actor, $context) {
            $subject->forceFill(['status' => $to] + $attributes)->save();

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'status_changed',
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'changes' => array_filter([
                    'status' => ['from' => $from, 'to' => $to],
                    ...$changes,
                    'reason' => $context['reason'] ?? null,
                ]),
            ]);
        });

        // Side effects (queued jobs/notifications) only fire after the commit.
        $this->sideEffects($subject, $from, $to, $context);

        return $subject;
    }
}
