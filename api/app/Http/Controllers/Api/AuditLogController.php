<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesIndexQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class AuditLogController extends Controller
{
    use HandlesIndexQueries;

    #[OA\Get(
        path: '/audit-logs',
        tags: ['Audit'],
        summary: 'List audit log entries (admin only)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'action', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'subject_type', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated audit entries'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->isAdmin(), 403);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->filter($request)
            ->latest();

        return AuditLogResource::collection($this->paginated($logs, $request, defaultPerPage: 25));
    }
}
