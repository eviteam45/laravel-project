<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'contractor_id' => ['sometimes', 'integer', 'exists:contractors,id', Rule::prohibitedIf(fn () => ! $this->user()->isAdmin())],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'capacity_kw' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'install_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
