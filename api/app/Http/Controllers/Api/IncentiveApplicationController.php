<?php

namespace App\Http\Controllers\Api;

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

class IncentiveApplicationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', IncentiveApplication::class);

        $applications = IncentiveApplication::query()
            ->visibleTo($request->user())
            // Eager-load the parent project (+ its contractor for region/company)
            // so the resource never triggers a per-row query (N+1).
            ->with(['project:id,name,contractor_id,customer_id', 'project.contractor:id,company_name,region'])
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->whereIn('status', array_filter(explode(',', (string) $request->query('status'))));
            })
            ->when($request->filled('project_id'), function ($q) use ($request) {
                return $q->where('project_id', $request->integer('project_id'));
            })
            // contractor + region live on the parent project / its contractor
            ->when($request->filled('contractor_id'), function ($q) use ($request) {
                $q->whereHas('project', fn ($p) => $p->where('contractor_id', $request->integer('contractor_id')));
            })
            ->when($request->filled('region'), function ($q) use ($request) {
                $q->whereHas('project.contractor', fn ($c) => $c->where('region', $request->query('region')));
            })
            // free-text search across the project name + contractor company
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->query('search').'%';
                $q->whereHas('project', function ($p) use ($term) {
                    $p->where('name', 'like', $term)
                        ->orWhereHas('contractor', fn ($c) => $c->where('company_name', 'like', $term));
                });
            })
            ->when($request->filled('submitted_from'), function ($q) use ($request) {
                return $q->whereDate('submitted_at', '>=', $request->date('submitted_from'));
            })
            ->when($request->filled('submitted_to'), function ($q) use ($request) {
                return $q->whereDate('submitted_at', '<=', $request->date('submitted_to'));
            });

        // Whitelisted sorting, newest first by default, with a stable id tiebreaker.
        $sortable = ['status', 'submitted_at', 'created_at', 'updated_at'];
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $applications->orderBy($sort, $dir)->orderBy('id', $dir);

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return IncentiveApplicationResource::collection(
            $applications->paginate($perPage)->withQueryString()
        );
    }

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

    public function show(IncentiveApplication $application): IncentiveApplicationResource
    {
        $this->authorize('view', $application);

        return new IncentiveApplicationResource(
            $application->load(['project:id,name,contractor_id,customer_id', 'steps', 'documents'])
        );
    }

    /**
     * Submit the application: all steps must be complete and at least one
     * document uploaded. Moves status draft → submitted.
     */
    public function submit(
        Request $request,
        IncentiveApplication $application,
        ApplicationStatusManager $manager,
    ): IncentiveApplicationResource {
        $this->authorize('update', $application);

        // in_progress → submitted. The manager enforces the edge, role, and the
        // "all steps complete + a document present" gating, and records the audit.
        $manager->transition($application, 'submitted', $request->user());

        return new IncentiveApplicationResource(
            $application->fresh()->load(['project:id,name,contractor_id,customer_id', 'steps', 'documents'])
        );
    }

    public function destroy(IncentiveApplication $application): JsonResponse
    {
        $this->authorize('delete', $application);

        // Once submitted, an application is part of the record and can't be deleted.
        if (in_array($application->status, ['submitted', 'under_review', 'reserved', 'paid'], true)) {
            throw ValidationException::withMessages([
                'application' => ['Cannot delete an application that has been submitted.'],
            ]);
        }

        $application->delete();

        return response()->json(['message' => 'Application deleted.']);
    }
}
