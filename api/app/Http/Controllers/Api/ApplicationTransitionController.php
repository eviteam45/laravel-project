<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IncentiveApplicationResource;
use App\Models\IncentiveApplication;
use App\Services\ApplicationStatusManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationTransitionController extends Controller
{
    /**
     * Move an application to a new status (reviewer/admin action).
     */
    public function store(
        Request $request,
        IncentiveApplication $application,
        ApplicationStatusManager $manager,
    ): IncentiveApplicationResource {
        // Per-edge role authorization is enforced inside the manager.
        $this->authorize('view', $application);

        $data = $request->validate([
            'to' => ['required', Rule::in(IncentiveApplication::STATUSES)],
            'incentive_amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $manager->transition($application, $data['to'], $request->user(), [
            'incentive_amount' => $data['incentive_amount'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);

        return new IncentiveApplicationResource(
            $application->fresh()->load(['project:id,name,contractor_id,customer_id', 'steps', 'documents'])
        );
    }
}
