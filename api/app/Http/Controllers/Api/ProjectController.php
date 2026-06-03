<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    /** Columns that may be sorted on. */
    private const SORTABLE = ['name', 'status', 'capacity_kw', 'install_date', 'created_at'];

    /**
     * Filterable, sortable, paginated index — scoped to the current user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {

        $this->authorize('viewAny', Project::class);

        $user = $request->user();

        $projects = Project::query()
            ->visibleTo($user)
            ->with(['contractor:id,company_name', 'customer:id,full_name'])
            ->withCount('batterySystems')
            // status=draft,completed  → whereIn
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->whereIn('status', array_filter(explode(',', (string) $request->query('status'))));
            })
            ->when($request->filled('contractor_id'), function ($q) use ($request) {
                return $q->where('contractor_id', $request->integer('contractor_id'));
            })
            ->when($request->filled('customer_id'), function ($q) use ($request) {
                return $q->where('customer_id', $request->integer('customer_id'));
            })
            // region lives on the contractor — filter through the relation
            ->when($request->filled('region'), function ($q) use ($request) {
                $q->whereHas('contractor', fn ($c) => $c->where('region', $request->query('region')));
            })
            // free-text search across name + address
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->query('search').'%';
                $q->where(function ($sub) use ($term) {
                    return $sub->where('name', 'like', $term)->orWhere('address', 'like', $term);
                });
            })
            ->when($request->filled('min_capacity'), function ($q) use ($request) {
                return $q->where('capacity_kw', '>=', $request->float('min_capacity'));
            })
            ->when($request->filled('max_capacity'), function ($q) use ($request) {
                return $q->where('capacity_kw', '<=', $request->float('max_capacity'));
            })
            ->when($request->filled('install_from'), function ($q) use ($request) {
                return $q->whereDate('install_date', '>=', $request->date('install_from'));
            })
            ->when($request->filled('install_to'), function ($q) use ($request) {
                return $q->whereDate('install_date', '<=', $request->date('install_to'));
            })
            // has_application=1 / has_application=0
            ->when($request->has('has_application'), function ($q) use ($request) {
                $request->boolean('has_application')
                    ? $q->whereHas('application')
                    : $q->whereDoesntHave('application');
            });

        // Whitelisted sorting, newest first by default, with a stable id tiebreaker
        // so pages stay deterministic when the sort column has duplicate values.
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $projects->orderBy($sort, $dir)->orderBy('id', $dir);

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return ProjectResource::collection(
            $projects->paginate($perPage)->withQueryString()
        );
    }

    public function store(StoreProjectRequest $request): ProjectResource
    {
        $data = $request->validated();
        $user = $request->user();

        // Contractors create under their own profile; an admin must name one.
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

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return new ProjectResource(
            $project->load(['contractor:id,company_name', 'customer:id,full_name'])
        );
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        // Don't delete a project whose application is in review or funded.
        if ($project->application()->whereIn('status', ['submitted', 'under_review', 'reserved', 'paid'])->exists()) {
            throw ValidationException::withMessages([
                'project' => ['Cannot delete a project with an application under review or funded.'],
            ]);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }
}
