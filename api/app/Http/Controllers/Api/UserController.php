<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesIndexQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserRoleManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    use HandlesIndexQueries;

    #[OA\Get(
        path: '/users',
        tags: ['Users'],
        summary: 'List users (admin only)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'name + email', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role', in: 'query', schema: new OA\Schema(type: 'string', enum: ['admin', 'contractor', 'customer'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated users'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->filter($request)->latest();

        return UserResource::collection($this->paginated($users, $request));
    }

    #[OA\Patch(
        path: '/users/{user}/role',
        tags: ['Users'],
        summary: "Change a user's role (admin only; backfills profile)",
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['role'],
            properties: [new OA\Property(property: 'role', type: 'string', enum: ['admin', 'contractor', 'customer'])]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Updated user'),
            new OA\Response(response: 422, description: 'Invalid role or own account'),
        ]
    )]
    public function updateRole(UpdateUserRoleRequest $request, User $user, UserRoleManager $roleManager): UserResource
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'role' => ['You cannot change your own role.'],
            ]);
        }

        $roleManager->changeRole($user, $request->validated()['role']);

        return new UserResource($user->fresh());
    }
}
