<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesIndexQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContractorRequest;
use App\Http\Requests\UpdateContractorRequest;
use App\Http\Resources\ContractorResource;
use App\Models\Contractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ContractorController extends Controller
{
    use HandlesIndexQueries;

    #[OA\Get(
        path: '/contractors/options',
        tags: ['Contractors'],
        summary: 'Lightweight contractor picker list (admin)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: '[{id, company_name}]')]
    )]
    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contractor::class);

        $contractors = Contractor::query()
            ->when($request->filled('search'), fn ($q) => $q->where('company_name', 'like', '%'.$request->query('search').'%'))
            ->orderBy('company_name')
            ->limit(100)
            ->get(['id', 'company_name']);

        return response()->json(['data' => $contractors]);
    }

    #[OA\Get(
        path: '/contractors',
        tags: ['Contractors'],
        summary: 'List contractors (admin only)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'region', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated contractors')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contractor::class);

        $contractors = Contractor::query()
            ->with('user:id,name,email')
            ->withCount('projects')
            ->filter($request)
            ->latest();

        return ContractorResource::collection($this->paginated($contractors, $request));
    }

    #[OA\Post(
        path: '/contractors',
        tags: ['Contractors'],
        summary: 'Provision a contractor + user account (admin only)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'password', 'company_name'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'company_name', type: 'string'),
                new OA\Property(property: 'region', type: 'string'),
                new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'pending']),
            ]
        )),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(StoreContractorRequest $request): ContractorResource
    {
        $contractor = Contractor::provision($request->validated());

        return new ContractorResource($contractor->load('user:id,name,email'));
    }

    #[OA\Get(
        path: '/contractors/{contractor}',
        tags: ['Contractors'],
        summary: 'Show a contractor (admin only)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'contractor', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Contractor')]
    )]
    public function show(Contractor $contractor): ContractorResource
    {
        $this->authorize('view', $contractor);

        return new ContractorResource($contractor->load('user:id,name,email')->loadCount('projects'));
    }

    #[OA\Put(
        path: '/contractors/{contractor}',
        tags: ['Contractors'],
        summary: 'Update a contractor (admin only)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'contractor', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'company_name', type: 'string'),
            new OA\Property(property: 'region', type: 'string'),
            new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'pending']),
        ])),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(UpdateContractorRequest $request, Contractor $contractor): ContractorResource
    {
        $contractor->update($request->validated());

        return new ContractorResource($contractor->load('user:id,name,email'));
    }

    #[OA\Delete(
        path: '/contractors/{contractor}',
        tags: ['Contractors'],
        summary: 'Delete a contractor (admin only; blocked if it has projects)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'contractor', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 422, description: 'Has projects'),
        ]
    )]
    public function destroy(Contractor $contractor): JsonResponse
    {
        $this->authorize('delete', $contractor);

        if ($contractor->projects()->exists()) {
            throw ValidationException::withMessages([
                'contractor' => ['Cannot delete a contractor that still has projects.'],
            ]);
        }

        $contractor->deleteWithUser();

        return response()->json(['message' => 'Contractor deleted.']);
    }
}
