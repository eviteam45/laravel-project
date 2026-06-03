<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->latest()
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return NotificationResource::collection($notifications);
    }

    public function markRead(Request $request, Notification $notification): NotificationResource
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->markAsRead();

        return new NotificationResource($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['marked_read' => $updated]);
    }
}
