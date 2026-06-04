<?php

namespace App\Http\Requests;

use App\Models\Contractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Contractor::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'company_name' => ['required', 'string', 'max:255'],
            'license_no' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'region' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'pending'])],
        ];
    }
}
