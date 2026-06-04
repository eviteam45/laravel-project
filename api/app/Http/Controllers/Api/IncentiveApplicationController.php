<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesIndexQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncentiveApplicationRequest;
use App\Http\Resources\IncentiveApplicationResource;
use App\Models\IncentiveApplication;
use App\Models\Project;
use App\Services\ApplicationStatusManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class IncentiveApplicationController extends Controller
{
    use HandlesIndexQueries;

    #[OA\Get(
        path: '/applications',
        tags: ['Applications'],
        summary: 'List applications (role-scoped, filterable, sortable, paginated)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', description: 'CSV', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'project_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'contractor_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'region', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', description: 'project name + contractor', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['status', 'submitted_at', 'created_at', 'updated_at'])),
            new OA\Parameter(name: 'dir', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated applications')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', IncentiveApplication::class);

        $applications = IncentiveApplication::query()
            ->visibleTo($request->user())
            ->with(['project:id,name,contractor_id,customer_id', 'project.contractor:id,company_name,region'])
            ->filter($request);

        return IncentiveApplicationResource::collection(
            $this->paginated($applications, $request, IncentiveApplication::SORTABLE)
        );
    }

    #[OA\Post(
        path: '/applications',
        tags: ['Applications'],
        summary: 'Create the (single) application for a project',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['project_id'],
            properties: [new OA\Property(property: 'project_id', type: 'integer')]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created (status=started)'),
            new OA\Response(response: 422, description: 'Project already has an application'),
        ]
    )]
    public function store(StoreIncentiveApplicationRequest $request): IncentiveApplicationResource
    {
        $project = Project::findOrFail($request->validated('project_id'));

        $this->authorize('create', [IncentiveApplication::class, $project]);
        if ($project->application()->exists()) {
            throw ValidationException::withMessages([
                'project_id' => ['This project already has an incentive application.'],
            ]);
        }

        $application = $project->application()->create([
            'status' => 'started',
            'current_step' => IncentiveApplication::STEP_KEYS[0],
        ]);

        return new IncentiveApplicationResource($application->load('steps'));
    }

    #[OA\Get(
        path: '/applications/{application}',
        tags: ['Applications'],
        summary: 'Show an application with steps and documents',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'application', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Application')]
    )]
    public function show(IncentiveApplication $application): IncentiveApplicationResource
    {
        $this->authorize('view', $application);

        return new IncentiveApplicationResource(
            $application->load(['project:id,name,contractor_id,customer_id', 'steps', 'documents'])
        );
    }

    #[OA\Post(
        path: '/applications/{application}/submit',
        tags: ['Applications'],
        summary: 'Submit the application (requires all steps complete + a document)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'application', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Submitted'),
            new OA\Response(response: 422, description: 'Incomplete steps or missing documents'),
        ]
    )]
    public function submit(
        Request $request,
        IncentiveApplication $application,
        ApplicationStatusManager $manager,
    ): IncentiveApplicationResource {
        $this->authorize('update', $application);

        $manager->transition($application, 'submitted', $request->user());

        return new IncentiveApplicationResource(
            $application->fresh()->load(['project:id,name,contractor_id,customer_id', 'steps', 'documents'])
        );
    }

    #[OA\Delete(
        path: '/applications/{application}',
        tags: ['Applications'],
        summary: 'Delete a draft application (blocked once submitted)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'application', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 422, description: 'Already submitted'),
        ]
    )]
    public function destroy(IncentiveApplication $application): JsonResponse
    {
        $this->authorize('delete', $application);

        if ($application->isLocked()) {
            throw ValidationException::withMessages([
                'application' => ['Cannot delete an application that has been submitted.'],
            ]);
        }

        $application->delete();

        return response()->json(['message' => 'Application deleted.']);
    }
}
