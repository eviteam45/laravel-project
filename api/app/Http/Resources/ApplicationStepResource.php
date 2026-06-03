<?php

namespace App\Http\Resources;

use App\Models\ApplicationStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationStep
 */
class ApplicationStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'step_key' => $this->step_key,
            // Named "fields" (not "data") to avoid colliding with the JsonResource
            // "data" wrapper key, which would suppress wrapping of this resource.
            'fields' => $this->data,
            'completed_at' => $this->completed_at,
            'is_complete' => $this->completed_at !== null,
        ];
    }
}
