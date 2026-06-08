<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {

        return $this->user()->can('view', $this->route('application'));
    }

    public function rules(): array
    {
        return [
            'to' => ['required', Rule::enum(ApplicationStatus::class)],
            'incentive_amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
