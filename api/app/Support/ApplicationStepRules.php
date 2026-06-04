<?php

namespace App\Support;

use InvalidArgumentException;

class ApplicationStepRules
{
    public static function for(string $stepKey): array
    {
        return match ($stepKey) {
            'eligibility' => [
                'owns_property' => ['required', 'boolean'],
                'utility_provider' => ['required', 'string', 'max:255'],
                'average_monthly_bill' => ['required', 'numeric', 'min:0'],
            ],
            'system' => [
                'battery_oem' => ['required', 'string', 'max:255'],
                'battery_model' => ['required', 'string', 'max:255'],
                'quantity' => ['required', 'integer', 'min:1'],
                'usable_capacity_kwh' => ['required', 'numeric', 'min:0'],
            ],

            'documents' => [],
            'review' => [
                'accepted_terms' => ['required', 'accepted'],
            ],
            default => throw new InvalidArgumentException("Unknown step: {$stepKey}"),
        };
    }
}
