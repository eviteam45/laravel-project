<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\IncentiveApplication;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private function disk(): string
    {
        return config('documents.disk');
    }

    #[OA\Post(
        path: '/applications/{application}/documents',
        tags: ['Documents'],
        summary: 'Upload a document to an application',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'application', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(required: ['file', 'type'], properties: [
                new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'pdf/jpg/jpeg/png, ≤10MB'),
                new OA\Property(property: 'type', type: 'string'),
            ])
        )),
        responses: [
            new OA\Response(response: 201, description: 'Uploaded (with signed download_url)'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function storeForApplication(UploadDocumentRequest $request, IncentiveApplication $application): DocumentResource
    {
        return $this->upload($request, $application);
    }

    #[OA\Post(
        path: '/projects/{project}/documents',
        tags: ['Documents'],
        summary: 'Upload a document to a project',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(required: ['file', 'type'], properties: [
                new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'pdf/jpg/jpeg/png, ≤10MB'),
                new OA\Property(property: 'type', type: 'string'),
            ])
        )),
        responses: [
            new OA\Response(response: 201, description: 'Uploaded'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function storeForProject(UploadDocumentRequest $request, Project $project): DocumentResource
    {
        return $this->upload($request, $project);
    }

    private function upload(UploadDocumentRequest $request, Model $owner): DocumentResource
    {
        $path = $request->file('file')->store("documents/{$owner->getTable()}/{$owner->getKey()}", $this->disk());

        $documents = $owner->documents();
        $document = $documents->create([
            'type' => $request->string('type'),
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        return new DocumentResource($document);
    }

    #[OA\Get(
        path: '/documents/{document}/download',
        tags: ['Documents'],
        summary: 'Stream a private document (temporary signed URL — no token)',
        parameters: [new OA\Parameter(name: 'document', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'File stream'),
            new OA\Response(response: 403, description: 'Invalid/expired signature'),
        ]
    )]
    public function download(Request $request, Document $document): StreamedResponse
    {
        $user = User::find($request->integer('u'));
        abort_unless($user && Gate::forUser($user)->allows('view', $document), 403);

        abort_unless(Storage::disk($this->disk())->exists($document->file_path), 404);

        return Storage::disk($this->disk())->download($document->file_path);
    }

    #[OA\Delete(
        path: '/documents/{document}',
        tags: ['Documents'],
        summary: 'Delete a document (row + stored file)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'document', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function destroy(Document $document): JsonResponse
    {

        $this->authorize('update', $document->documentable);

        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }
}
