<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Contractors create their own projects; admins may target any contractor.
            'contractor_id' => [
                'nullable', // contractors create under their own profile
                Rule::requiredIf(fn () => $this->user()->isAdmin()),
                'integer',
                'exists:contractors,id',
            ],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            // Status is owned by the workflow state machine, not set on create.
            'address' => ['nullable', 'string', 'max:255'],
            'capacity_kw' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'install_date' => ['nullable', 'date'],
        ];
    }
}
