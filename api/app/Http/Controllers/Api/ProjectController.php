<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesIndexQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    use HandlesIndexQueries;

    #[OA\Get(
        path: '/projects',
        tags: ['Projects'],
        summary: 'List projects (role-scoped, filterable, sortable, paginated)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', description: 'CSV of statuses', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'region', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'contractor_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'customer_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'has_application', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', description: 'name + address', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: Project::SORTABLE)),
            new OA\Parameter(name: 'dir', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated project list')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->visibleTo($request->user())
            ->with(['contractor:id,company_name', 'customer:id,full_name'])
            ->withCount('batterySystems')
            ->filter($request);

        return ProjectResource::collection($this->paginated($projects, $request, Project::SORTABLE));
    }

    #[OA\Post(
        path: '/projects',
        tags: ['Projects'],
        summary: 'Create a project (contractor owns it; admin passes contractor_id)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'customer_id'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'customer_id', type: 'integer'),
                new OA\Property(property: 'contractor_id', type: 'integer', description: 'required for admins'),
                new OA\Property(property: 'address', type: 'string'),
                new OA\Property(property: 'capacity_kw', type: 'number'),
                new OA\Property(property: 'install_date', type: 'string', format: 'date'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreProjectRequest $request): ProjectResource
    {
        $data = $request->validated();
        $user = $request->user();

        $contractorId = $user->contractor?->id ?? ($data['contractor_id'] ?? null);

        if (! $contractorId) {
            throw ValidationException::withMessages([
                'contractor_id' => ['Select a contractor to create this project under.'],
            ]);
        }

        $data['contractor_id'] = $contractorId;

        $project = Project::create($data);

        return new ProjectResource(
            $project->load(['contractor:id,company_name', 'customer:id,full_name'])
        );
    }

    #[OA\Get(
        path: '/projects/{project}',
        tags: ['Projects'],
        summary: 'Show a project with battery systems, documents and application',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Project'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource(
            $project->load([
                'contractor:id,company_name',
                'customer:id,full_name',
                'batterySystems',
                'documents',
                'application',
            ])
        );
    }

    #[OA\Put(
        path: '/projects/{project}',
        tags: ['Projects'],
        summary: 'Update a project (status changes go through /transition)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'customer_id', type: 'integer'),
            new OA\Property(property: 'address', type: 'string'),
            new OA\Property(property: 'capacity_kw', type: 'number'),
            new OA\Property(property: 'install_date', type: 'string', format: 'date'),
        ])),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return new ProjectResource(
            $project->load(['contractor:id,company_name', 'customer:id,full_name'])
        );
    }

    #[OA\Delete(
        path: '/projects/{project}',
        tags: ['Projects'],
        summary: 'Delete a project (blocked once its application is in review/funded)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 422, description: 'Has an active application'),
        ]
    )]
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        if ($project->hasLockedApplication()) {
            throw ValidationException::withMessages([
                'project' => ['Cannot delete a project with an application under review or funded.'],
            ]);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }
}
