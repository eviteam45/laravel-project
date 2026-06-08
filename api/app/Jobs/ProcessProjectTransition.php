<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\TransitionNotifier;
use App\Support\DashboardCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessProjectTransition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $projectId,
        public string $from,
        public string $to,
        public ?int $actorId = null,
    ) {}

    public function handle(TransitionNotifier $notifier): void
    {
        $project = Project::with(['contractor.user', 'customer.user'])->find($this->projectId);

        if (! $project) {
            return;
        }

        $notifier->notifyProjectTransition($project, $this->from, $this->to, $this->actorId);

        DashboardCache::forget(
            $notifier->recipients($project)->pluck('id')->push($this->actorId)
        );
    }
}
