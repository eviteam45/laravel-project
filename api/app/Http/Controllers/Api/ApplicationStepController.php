<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveApplicationStepRequest;
use App\Http\Resources\ApplicationStepResource;
use App\Models\IncentiveApplication;
use App\Services\ApplicationStatusManager;
use OpenApi\Attributes as OA;

class ApplicationStepController extends Controller
{
    #[OA\Put(
        path: '/applications/{application}/steps/{stepKey}',
        tags: ['Applications'],
        summary: 'Save a wizard step (draft when complete=false, strict when true)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'application', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'stepKey', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['eligibility', 'system', 'documents', 'banking', 'review'])),
        ],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'object'),
            new OA\Property(property: 'complete', type: 'boolean'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Step saved'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(
        SaveApplicationStepRequest $request,
        IncentiveApplication $application,
        string $stepKey,
        ApplicationStatusManager $status,
    ): ApplicationStepResource {
        if (! in_array($stepKey, IncentiveApplication::STEP_KEYS, true)) {
            abort(404, 'Unknown step.');
        }

        $step = $application->steps()->updateOrCreate(
            ['step_key' => $stepKey],
            [
                'data' => $request->input('data', []),
                'completed_at' => $request->boolean('complete') ? now() : null,
            ],
        );

        if ($application->status === 'started') {
            $status->transition($application, 'in_progress', $request->user());
        }

        $application->recomputeCurrentStep();

        return new ApplicationStepResource($step);
    }
}
