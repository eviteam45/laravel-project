<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    #[OA\Get(
        path: '/notifications',
        tags: ['Notifications'],
        summary: "List the caller's notifications",
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'unread', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated notifications')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->latest()
            ->latest('id')
            ->cursorPaginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return NotificationResource::collection($notifications)
            ->additional(['unread_count' => $this->unreadCountFor($request)]);
    }

    #[OA\Post(
        path: '/notifications/{notification}/read',
        tags: ['Notifications'],
        summary: 'Mark one notification read',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'notification', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Marked read'),
            new OA\Response(response: 403, description: "Not the caller's notification"),
        ]
    )]
    public function markRead(Request $request, Notification $notification): NotificationResource
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->markAsRead();

        return new NotificationResource($notification);
    }

    #[OA\Post(
        path: '/notifications/read-all',
        tags: ['Notifications'],
        summary: 'Mark all of the caller\'s notifications read',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'marked_read count')]
    )]
    public function markAllRead(Request $request): JsonResponse
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['marked_read' => $updated]);
    }

    #[OA\Get(
        path: '/notifications/unread-count',
        tags: ['Notifications'],
        summary: 'Cheap unread-count for the notification badge',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: '{ unread_count }')]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $this->unreadCountFor($request)]);
    }

    private function unreadCountFor(Request $request): int
    {
        return Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count();
    }
}
