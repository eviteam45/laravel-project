<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TransitionNotifier
{
    public const ADMIN_RECIPIENTS_CACHE_KEY = 'transition.admin-recipients';

    public function recipients(Project $project, ?int $exceptActorId = null): Collection
    {
        return collect([
            $project->contractor?->user,
            $project->customer?->user,
        ])
            ->merge($this->admins())
            ->filter(fn (?User $u) => $u && $u->id !== $exceptActorId)
            ->unique('id')
            ->values();
    }

    private function admins(): Collection
    {
        return Cache::remember(
            self::ADMIN_RECIPIENTS_CACHE_KEY,
            now()->addMinutes(5),
            fn () => User::where('role', Role::Admin->value)->get(),
        );
    }

    public function record(User $user, string $type, array $data, array $dedupeKeys): bool
    {
        $query = Notification::query()
            ->where('user_id', $user->id)
            ->where('type', $type);

        foreach ($dedupeKeys as $key) {
            $query->where("data->{$key}", $data[$key] ?? null);
        }

        if ($query->exists()) {
            return false;
        }

        try {
            Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'data' => $data,
            ]);
        } catch (QueryException $e) {

            return false;
        }

        return true;
    }

    public function notifyProjectTransition(Project $project, string $from, string $to, ?int $actorId = null): void
    {
        $project->loadMissing(['contractor.user', 'customer.user']);

        $data = ['project_id' => $project->id, 'from' => $from, 'to' => $to];

        foreach ($this->recipients($project, $actorId) as $recipient) {
            $this->record($recipient, "project_{$to}", $data, ['project_id']);
        }
    }
}
