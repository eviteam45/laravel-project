<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'file_name' => basename($this->file_path),
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at,

            'download_url' => URL::temporarySignedRoute(
                'documents.download',
                now()->addMinutes(30),
                ['document' => $this->id],
            ),
        ];
    }
}
