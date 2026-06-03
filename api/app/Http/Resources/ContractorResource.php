<?php

namespace App\Http\Resources;

use App\Models\Contractor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contractor
 */
class ContractorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'license_no' => $this->license_no,
            'phone' => $this->phone,
            'region' => $this->region,
            'status' => $this->status,
            'projects_count' => $this->whenCounted('projects'),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
