<?php

namespace App\Http\Resources;

use App\Models\BatterySystem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BatterySystem
 */
class BatterySystemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'oem' => $this->oem,
            'model' => $this->model,
            'quantity' => $this->quantity,
            'usable_capacity_kwh' => $this->usable_capacity_kwh,
        ];
    }
}
