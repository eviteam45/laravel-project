<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\IncentiveApplication;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /** Private disk used for all uploaded documents. */
    private const DISK = 'local';

    public function storeForApplication(Request $request, IncentiveApplication $application): DocumentResource
    {
        return $this->upload($request, $application);
    }

    public function storeForProject(Request $request, Project $project): DocumentResource
    {
        return $this->upload($request, $project);
    }

    /**
     * Upload a document and attach it (polymorphically) to its owner.
     */
    private function upload(Request $request, Model $owner): DocumentResource
    {
        $this->authorize('update', $owner);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'type' => ['required', 'string', 'max:50'],
        ]);

        $path = $request->file('file')->store("documents/{$owner->getTable()}/{$owner->getKey()}", self::DISK);

        /** @var MorphMany $documents */
        $documents = $owner->documents();
        $document = $documents->create([
            'type' => $request->string('type'),
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        return new DocumentResource($document);
    }

    /**
     * Stream a private document. Reached only via a valid temporary signed URL
     * (the `signed` middleware on the route enforces this).
     */
    public function download(Document $document): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($document->file_path), 404);

        return Storage::disk(self::DISK)->download($document->file_path);
    }

    public function destroy(Document $document): JsonResponse
    {
        // Authorize against the document's owner (a Project or IncentiveApplication).
        $this->authorize('update', $document->documentable);

        // The Document model's `deleted` hook removes the backing file.
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }
}
