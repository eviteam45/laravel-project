<?php

namespace App\Http\Requests;

use App\Models\IncentiveApplication;
use App\Support\ApplicationStepRules;
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
            'data' => ['sometimes', 'array'],
        ];

        if ($this->boolean('complete')) {
            $stepKey = $this->route('stepKey');
            if (in_array($stepKey, IncentiveApplication::STEP_KEYS, true)) {
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

            if (! in_array($application->status, ['started', 'in_progress'], true)) {
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
