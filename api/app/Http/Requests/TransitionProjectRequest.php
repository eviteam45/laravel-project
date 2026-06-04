<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'to' => ['required', Rule::in(Project::STATUSES)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
