<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],

            'role' => ['required', Rule::in([Role::Contractor->value, Role::Customer->value])],
            'phone' => ['nullable', 'string', 'max:50'],

            'company_name' => ['nullable', 'required_if:role,contractor', 'string', 'max:255'],
            'license_no' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:255'],

            'full_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
