<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContractorResource;
use App\Models\Contractor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin-only contractor management (authorization via ContractorPolicy::before).
 */
class ContractorController extends Controller
{
    /**
     * Lightweight contractor list (id + company) for the admin project-create picker.
     */
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

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contractor::class);

        $contractors = Contractor::query()
            ->with('user:id,name,email')
            ->withCount('projects')
            ->when($request->filled('search'), fn ($q) => $q->where('company_name', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('region'), fn ($q) => $q->where('region', $request->query('region')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest();

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return ContractorResource::collection($contractors->paginate($perPage)->withQueryString());
    }

    public function store(Request $request): ContractorResource
    {
        $this->authorize('create', Contractor::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'company_name' => ['required', 'string', 'max:255'],
            'license_no' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'region' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'pending'])],
        ]);

        $contractor = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'contractor',
            ]);

            return $user->contractor()->create([
                'company_name' => $data['company_name'],
                'license_no' => $data['license_no'] ?? null,
                'phone' => $data['phone'] ?? null,
                'region' => $data['region'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);
        });

        return new ContractorResource($contractor->load('user:id,name,email'));
    }

    public function show(Contractor $contractor): ContractorResource
    {
        $this->authorize('view', $contractor);

        return new ContractorResource($contractor->load('user:id,name,email')->loadCount('projects'));
    }

    public function update(Request $request, Contractor $contractor): ContractorResource
    {
        $this->authorize('update', $contractor);

        $data = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'license_no' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'region' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'pending'])],
        ]);

        $contractor->update($data);

        return new ContractorResource($contractor->load('user:id,name,email'));
    }

    public function destroy(Contractor $contractor): JsonResponse
    {
        $this->authorize('delete', $contractor);

        if ($contractor->projects()->exists()) {
            throw ValidationException::withMessages([
                'contractor' => ['Cannot delete a contractor that still has projects.'],
            ]);
        }

        DB::transaction(function () use ($contractor) {
            $userId = $contractor->user_id;
            $contractor->delete();
            User::whereKey($userId)->delete();
        });

        return response()->json(['message' => 'Contractor deleted.']);
    }
}
