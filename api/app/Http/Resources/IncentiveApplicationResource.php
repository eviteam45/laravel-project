<?php

namespace App\Http\Resources;

use App\Models\IncentiveApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IncentiveApplication
 */
class IncentiveApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'submitted_at' => $this->submitted_at,
            'incentive_amount' => $this->incentive_amount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Ordered list of step keys for the wizard.
            'step_keys' => IncentiveApplication::STEP_KEYS,

            'steps' => ApplicationStepResource::collection($this->whenLoaded('steps')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'project' => new ProjectResource($this->whenLoaded('project')),
        ];
    }
}
