<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Document
 */
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
            // Short-lived signed URL — private files are never served directly.
            'download_url' => URL::temporarySignedRoute(
                'documents.download',
                now()->addMinutes(30),
                ['document' => $this->id],
            ),
        ];
    }
}
