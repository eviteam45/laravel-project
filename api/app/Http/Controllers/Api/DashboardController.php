<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\IncentiveApplicationResource;
use App\Models\IncentiveApplication;
use App\Models\IncentivePayment;
use App\Models\Notification;
use App\Models\Project;
use App\Support\DashboardCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    private const CACHE_TTL_SECONDS = 60;

    #[OA\Get(
        path: '/dashboard/stats',
        tags: ['Dashboard'],
        summary: 'Role-scoped aggregate stats (grouped counts + totals)',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Stats object')]
    )]
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $payload = Cache::remember(DashboardCache::key($user->id), self::CACHE_TTL_SECONDS, function () use ($user, $request) {
            $projects = Project::query()->visibleTo($user);
            $applications = IncentiveApplication::query()->visibleTo($user);

            $payments = IncentivePayment::query()
                ->whereHas('application', fn (Builder $q) => $q->visibleTo($user));

            return [
                'projects' => [
                    'total' => (clone $projects)->count(),
                    'by_status' => $this->countByStatus($projects, ProjectStatus::values()),
                ],
                'applications' => [
                    'total' => (clone $applications)->count(),
                    'by_status' => $this->countByStatus($applications, ApplicationStatus::values()),
                ],
                'incentives' => [
                    'reserved_total' => (float) (clone $applications)
                        ->whereIn('status', ['reserved', 'paid'])->sum('incentive_amount'),
                    'paid_total' => (float) (clone $payments)->where('status', 'paid')->sum('amount'),
                    'scheduled_total' => (float) (clone $payments)->where('status', 'scheduled')->sum('amount'),
                ],
                'recent_applications' => IncentiveApplicationResource::collection(
                    (clone $applications)
                        ->with('project:id,name,contractor_id,customer_id')
                        ->latest('updated_at')
                        ->limit(5)
                        ->get()
                )->resolve($request),
            ];
        });

        $payload['notifications'] = [
            'unread_count' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
        ];

        return response()->json($payload);
    }

    private function countByStatus(Builder $query, array $statuses): array
    {
        $counts = (clone $query)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $out = [];
        foreach ($statuses as $status) {
            $out[$status] = (int) ($counts[$status] ?? 0);
        }

        return $out;
    }
}
