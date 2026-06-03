<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IncentiveApplicationResource;
use App\Models\IncentiveApplication;
use App\Models\IncentivePayment;
use App\Models\Notification;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Role-scoped aggregate stats for the dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = Project::query()->visibleTo($user);
        $applications = IncentiveApplication::query()->visibleTo($user);

        // Payments belonging to visible applications.
        $payments = IncentivePayment::query()
            ->whereHas('application', fn (Builder $q) => $q->visibleTo($user));

        return response()->json([
            'projects' => [
                'total' => (clone $projects)->count(),
                'by_status' => $this->countByStatus($projects, Project::STATUSES),
            ],
            'applications' => [
                'total' => (clone $applications)->count(),
                'by_status' => $this->countByStatus($applications, IncentiveApplication::STATUSES),
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
            ),
            'notifications' => [
                'unread_count' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
            ],
        ]);
    }

    /**
     * Count rows grouped by status, filling missing statuses with 0.
     *
     * @param  list<string>  $statuses
     */
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
