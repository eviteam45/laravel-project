<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionApplicationRequest;
use App\Http\Resources\IncentiveApplicationResource;
use App\Models\IncentiveApplication;
use App\Services\ApplicationStatusManager;
use OpenApi\Attributes as OA;

class ApplicationTransitionController extends Controller
{
    #[OA\Post(
        path: '/applications/{application}/transition',
        tags: ['Applications'],
        summary: 'Transition application status (state machine)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'application', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['to'],
            properties: [
                new OA\Property(property: 'to', type: 'string', enum: ['in_progress', 'submitted', 'under_review', 'reserved', 'paid', 'rejected', 'withdrawn']),
                new OA\Property(property: 'incentive_amount', type: 'number', description: 'required when to=reserved'),
                new OA\Property(property: 'reason', type: 'string'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Updated application'),
            new OA\Response(response: 422, description: 'Illegal transition'),
            new OA\Response(response: 403, description: 'Wrong role'),
        ]
    )]
    public function store(
        TransitionApplicationRequest $request,
        IncentiveApplication $application,
        ApplicationStatusManager $manager,
    ): IncentiveApplicationResource {
        $data = $request->validated();

        $manager->transition($application, $data['to'], $request->user(), [
            'incentive_amount' => $data['incentive_amount'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);

        return new IncentiveApplicationResource(
            $application->fresh()->load(['project:id,name,contractor_id,customer_id', 'steps', 'documents'])
        );
    }
}
