<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            'role' => ['required', Rule::in(['contractor', 'customer'])],
            'phone' => ['nullable', 'string', 'max:50'],

            'company_name' => ['nullable', 'required_if:role,contractor', 'string', 'max:255'],
            'license_no' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:255'],

            'full_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
