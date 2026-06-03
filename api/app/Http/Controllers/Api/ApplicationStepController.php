<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationStepResource;
use App\Models\IncentiveApplication;
use App\Services\ApplicationStatusManager;
use App\Support\ApplicationStepRules;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ApplicationStepController extends Controller
{
    /**
     * Save (create or update) a single step.
     *
     * Body: { data: {...}, complete: bool }
     *  - complete=false → save progress (lenient), step stays incomplete (resume here)
     *  - complete=true  → validate strictly and mark the step done
     */
    public function update(
        Request $request,
        IncentiveApplication $application,
        string $stepKey,
        ApplicationStatusManager $status,
    ): ApplicationStepResource {
        $this->authorize('update', $application);

        if (! in_array($stepKey, IncentiveApplication::STEP_KEYS, true)) {
            abort(404, 'Unknown step.');
        }

        if (! in_array($application->status, ['started', 'in_progress'], true)) {
            throw ValidationException::withMessages([
                'status' => ['This application can no longer be edited.'],
            ]);
        }

        $complete = $request->boolean('complete');
        $request->validate(['data' => ['sometimes', 'array']]);

        if ($complete) {
            // Strict per-step validation, nested under data.*
            $rules = (new Collection(ApplicationStepRules::for($stepKey)))
                ->mapWithKeys(fn ($rule, $field) => ["data.{$field}" => $rule])
                ->all();

            $request->validate($rules);

            // The documents step also requires at least one uploaded file.
            if ($stepKey === 'documents' && $application->documents()->doesntExist()) {
                throw ValidationException::withMessages([
                    'documents' => ['Upload at least one document before completing this step.'],
                ]);
            }
        }

        $step = $application->steps()->updateOrCreate(
            ['step_key' => $stepKey],
            [
                'data' => $request->input('data', []),
                'completed_at' => $complete ? now() : null,
            ],
        );

        // First edit advances the workflow started → in_progress.
        if ($application->status === 'started') {
            $status->transition($application, 'in_progress', $request->user());
        }

        // Move the resume pointer to the first remaining step.
        $application->recomputeCurrentStep();

        return new ApplicationStepResource($step);
    }
}
