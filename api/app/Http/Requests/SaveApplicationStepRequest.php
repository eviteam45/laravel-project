<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use App\Models\IncentiveApplication;
use App\Support\ApplicationStepRules;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveApplicationStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('application'));
    }

    public function rules(): array
    {
        $rules = [
            'complete' => ['sometimes', 'boolean'],
            'data' => ['sometimes', 'array', 'max:50'],
        ];

        $stepKey = $this->route('stepKey');

        if (in_array($stepKey, IncentiveApplication::STEP_KEYS, true)) {
            $allowed = array_keys(ApplicationStepRules::for($stepKey));

            $rules['data'][] = function (string $attribute, mixed $value, Closure $fail) use ($allowed) {
                if (is_array($value) && ($extra = array_diff(array_keys($value), $allowed))) {
                    $fail('These fields are not allowed for this step: '.implode(', ', $extra).'.');
                }
            };

            if ($this->boolean('complete')) {
                foreach (ApplicationStepRules::for($stepKey) as $field => $fieldRules) {
                    $rules["data.{$field}"] = $fieldRules;
                }
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {

            $application = $this->route('application');

            if (! in_array($application->status, [ApplicationStatus::Started, ApplicationStatus::InProgress], true)) {
                $validator->errors()->add('status', 'This application can no longer be edited.');

                return;
            }

            if ($this->boolean('complete')
                && $this->route('stepKey') === 'documents'
                && $application->documents()->doesntExist()) {
                $validator->errors()->add('documents', 'Upload at least one document before completing this step.');
            }
        });
    }
}
