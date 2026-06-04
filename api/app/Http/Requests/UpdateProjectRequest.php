<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],

            'address' => ['nullable', 'string', 'max:255'],
            'capacity_kw' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'install_date' => ['nullable', 'date'],
        ];
    }
}
