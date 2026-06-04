<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {

        $owner = $this->route('application') ?? $this->route('project');

        return $owner !== null && $this->user()->can('update', $owner);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'type' => ['required', 'string', 'max:50'],
        ];
    }
}
