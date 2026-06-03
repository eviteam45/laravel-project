<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'address' => $this->address,
            'capacity_kw' => $this->capacity_kw,
            'install_date' => $this->install_date?->toDateString(),
            'contractor_id' => $this->contractor_id,
            'customer_id' => $this->customer_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Lightweight related summaries (only when eager-loaded).
            'contractor' => $this->whenLoaded('contractor', fn () => [
                'id' => $this->contractor->id,
                'company_name' => $this->contractor->company_name,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'full_name' => $this->customer->full_name,
            ]),
            'battery_systems' => BatterySystemResource::collection($this->whenLoaded('batterySystems')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'application' => new IncentiveApplicationResource($this->whenLoaded('application')),
            'battery_systems_count' => $this->whenCounted('batterySystems'),
        ];
    }
}
