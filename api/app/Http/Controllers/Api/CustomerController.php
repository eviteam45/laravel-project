<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesIndexQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class CustomerController extends Controller
{
    use HandlesIndexQueries;

    #[OA\Get(
        path: '/customers/options',
        tags: ['Customers'],
        summary: 'Lightweight customer picker list (contractor or admin)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: '[{id, full_name, account_email}]')]
    )]
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

    #[OA\Get(
        path: '/customers',
        tags: ['Customers'],
        summary: 'List customers (admin only)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Paginated customers')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->with('user:id,name,email')
            ->withCount('projects')
            ->filter($request)
            ->latest();

        return CustomerResource::collection($this->paginated($customers, $request));
    }

    #[OA\Post(
        path: '/customers',
        tags: ['Customers'],
        summary: 'Provision a customer + user account (admin only)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'password'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'phone', type: 'string'),
                new OA\Property(property: 'address', type: 'string'),
            ]
        )),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(StoreCustomerRequest $request): CustomerResource
    {
        $customer = Customer::provision($request->validated());

        return new CustomerResource($customer->load('user:id,name,email'));
    }

    #[OA\Get(
        path: '/customers/{customer}',
        tags: ['Customers'],
        summary: 'Show a customer (admin only)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Customer')]
    )]
    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer->load('user:id,name,email')->loadCount('projects'));
    }

    #[OA\Put(
        path: '/customers/{customer}',
        tags: ['Customers'],
        summary: 'Update a customer (admin only)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'full_name', type: 'string'),
            new OA\Property(property: 'phone', type: 'string'),
            new OA\Property(property: 'address', type: 'string'),
            new OA\Property(property: 'account_email', type: 'string', format: 'email'),
        ])),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($customer->load('user:id,name,email'));
    }

    #[OA\Delete(
        path: '/customers/{customer}',
        tags: ['Customers'],
        summary: 'Delete a customer (admin only; blocked if it has projects)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 422, description: 'Has projects'),
        ]
    )]
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->projects()->exists()) {
            throw ValidationException::withMessages([
                'customer' => ['Cannot delete a customer that still has projects.'],
            ]);
        }

        $customer->deleteWithUser();

        return response()->json(['message' => 'Customer deleted.']);
    }
}
