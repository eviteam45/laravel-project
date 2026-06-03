<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectStatusManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectTransitionController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        ProjectStatusManager $manager,
    ): ProjectResource {
        // Per-edge role authorization is enforced inside the manager.
        $this->authorize('view', $project);

        $data = $request->validate([
            'to' => ['required', Rule::in(Project::STATUSES)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $manager->transition($project, $data['to'], $request->user(), [
            'reason' => $data['reason'] ?? null,
        ]);

        return new ProjectResource(
            $project->fresh()->load(['contractor:id,company_name', 'customer:id,full_name'])
        );
    }
}
