<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

class TransitionNotifier
{
    public function recipients(Project $project, ?int $exceptActorId = null): Collection
    {
        return collect([
            $project->contractor?->user,
            $project->customer?->user,
        ])
            ->merge(User::where('role', 'admin')->get())
            ->filter(fn (?User $u) => $u && $u->id !== $exceptActorId)
            ->unique('id')
            ->values();
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

        Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'data' => $data,
        ]);

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
