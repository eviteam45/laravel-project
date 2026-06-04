<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'step_key' => $this->step_key,

            'fields' => $this->data,
            'completed_at' => $this->completed_at,
            'is_complete' => $this->completed_at !== null,
        ];
    }
}
