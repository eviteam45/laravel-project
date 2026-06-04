<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('contractor'));
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'license_no' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'region' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'pending'])],
        ];
    }
}
