<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Admin-only customer management (authorization via CustomerPolicy::before).
 */
class CustomerController extends Controller
{
    /**
     * Lightweight customer list (id + name) for picker dropdowns.
     * Available to contractors and admins — the roles that create projects.
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isContractor(), 403);

        $customers = Customer::query()
            ->when($request->filled('search'), fn ($q) => $q->where('full_name', 'like', '%'.$request->query('search').'%'))
            ->orderBy('full_name')
            ->limit(100)
            ->get(['id', 'full_name', 'account_email']);

        return response()->json(['data' => $customers]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->with('user:id,name,email')
            ->withCount('projects')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->query('search').'%';
                $q->where(fn ($sub) => $sub->where('full_name', 'like', $term)->orWhere('account_email', 'like', $term));
            })
            ->latest();

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return CustomerResource::collection($customers->paginate($perPage)->withQueryString());
    }

    public function store(Request $request): CustomerResource
    {
        $this->authorize('create', Customer::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'customer',
            ]);

            return $user->customer()->create([
                'full_name' => $data['full_name'] ?? $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'account_email' => $data['email'],
            ]);
        });

        return new CustomerResource($customer->load('user:id,name,email'));
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer->load('user:id,name,email')->loadCount('projects'));
    }

    public function update(Request $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'account_email' => ['sometimes', 'email', 'max:255'],
        ]);

        $customer->update($data);

        return new CustomerResource($customer->load('user:id,name,email'));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->projects()->exists()) {
            throw ValidationException::withMessages([
                'customer' => ['Cannot delete a customer that still has projects.'],
            ]);
        }

        DB::transaction(function () use ($customer) {
            $userId = $customer->user_id;
            $customer->delete();
            User::whereKey($userId)->delete();
        });

        return response()->json(['message' => 'Customer deleted.']);
    }
}
