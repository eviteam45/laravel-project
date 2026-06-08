<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

abstract class StatusTransitionManager
{
    abstract public function graph(): array;

    abstract protected function ownerCheck(Model $subject, User $actor): bool;

    protected function mutations(Model $subject, string $to, array $context): array
    {
        return [[], []];
    }

    protected function sideEffects(Model $subject, string $from, string $to, User $actor, array $context): void {}

    public function canTransition(string $from, string $to): bool
    {
        return array_key_exists($to, $this->graph()[$from] ?? []);
    }

    public function allowedFor(string $from, User $actor): array
    {
        $edges = $this->graph()[$from] ?? [];

        if ($actor->isAdmin()) {
            return array_keys($edges);
        }

        return array_keys(array_filter($edges, fn (array $roles) => in_array($actor->role->value, $roles, true)));
    }

    public function transition(Model $subject, string $to, User $actor, array $context = []): Model
    {
        $from = $subject->status->value;

        if (! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'to' => ["Illegal transition: '{$from}' → '{$to}'."],
            ]);
        }

        if (! $actor->isAdmin()) {
            $roles = $this->graph()[$from][$to] ?? [];

            if (! in_array($actor->role->value, $roles, true) || ! $this->ownerCheck($subject, $actor)) {
                throw new AuthorizationException('Your role is not permitted to make this transition.');
            }
        }

        [$attributes, $changes] = $this->mutations($subject, $to, $context);

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

        $this->sideEffects($subject, $from, $to, $actor, $context);

        return $subject;
    }
}
