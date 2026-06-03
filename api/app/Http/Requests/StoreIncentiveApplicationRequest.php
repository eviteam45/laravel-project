<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncentiveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller authorizes against the parent project.
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ];
    }
}
