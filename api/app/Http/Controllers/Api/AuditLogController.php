<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin-only viewer of recent audit entries, filterable by action,
 * user, and subject type.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->isAdmin(), 403);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', 'like', '%'.$request->query('subject_type').'%'))
            ->latest()
            ->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        return AuditLogResource::collection($logs->withQueryString());
    }
}
