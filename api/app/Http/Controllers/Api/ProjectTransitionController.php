<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectStatusManager;
use OpenApi\Attributes as OA;

class ProjectTransitionController extends Controller
{
    #[OA\Post(
        path: '/projects/{project}/transition',
        tags: ['Projects'],
        summary: 'Transition project status (state machine)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['to'],
            properties: [
                new OA\Property(property: 'to', type: 'string', enum: ['submitted', 'in_review', 'approved', 'installed', 'closed', 'rejected']),
                new OA\Property(property: 'reason', type: 'string'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Updated project'),
            new OA\Response(response: 422, description: 'Illegal transition'),
            new OA\Response(response: 403, description: 'Wrong role'),
        ]
    )]
    public function store(
        TransitionProjectRequest $request,
        Project $project,
        ProjectStatusManager $manager,
    ): ProjectResource {
        $data = $request->validated();

        $manager->transition($project, $data['to'], $request->user(), [
            'reason' => $data['reason'] ?? null,
        ]);

        return new ProjectResource(
            $project->fresh()->load(['contractor:id,company_name', 'customer:id,full_name'])
        );
    }
}
