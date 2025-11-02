<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/notifications",
     *     tags={"Notifications"},
     *     summary="List user notifications",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications list",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="body", type="string"),
     *                 @OA\Property(property="is_read", type="boolean"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $uid = $request->user()->id;
        $limit = min(20, (int)$request->query('limit', 20));
        $scope = $request->query('scope', 'all');

        $q = UserNotification::where('owner_user_id', $uid);

        if ($scope === 'unread') {
            $q->where('status', 'unread');
        } elseif ($scope === 'upcoming') {
            $q->whereNotNull('scheduled_at')->where('scheduled_at', '>=', now());
        } elseif ($scope === 'past') {
            $q->where(function ($w) {
                $w->whereNull('scheduled_at')->orWhere('scheduled_at', '<', now());
            });
        }

        $q->orderByRaw('COALESCE(scheduled_at, created_at) DESC, id DESC');

        return [
            'data' => $q->limit($limit)->get(),
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/{notification}/read",
     *     tags={"Notifications"},
     *     summary="Mark notification as read",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="notification",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as read",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="is_read", type="boolean")
     *         )
     *     )
     * )
     */
    public function markRead(Request $request, UserNotification $notification)
    {
        abort_unless($notification->owner_user_id === $request->user()->id, 403);
        if ($notification->status === 'unread') {
            $notification->update(['status' => 'read', 'read_at' => now()]);
        }
        return $notification;
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/{notification}/done",
     *     tags={"Notifications"},
     *     summary="Mark notification as done",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="notification",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as done",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="is_done", type="boolean")
     *         )
     *     )
     * )
     */
    public function markDone(Request $request, UserNotification $notification)
    {
        abort_unless($notification->owner_user_id === $request->user()->id, 403);
        $notification->update(['status' => 'done', 'read_at' => now()]);
        return $notification;
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/bulk-read",
     *     tags={"Notifications"},
     *     summary="Mark multiple notifications as read",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="updated", type="integer")
     *         )
     *     )
     * )
     */
    public function bulkRead(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $uid = $request->user()->id;
        $count = UserNotification::where('owner_user_id', $uid)
            ->whereIn('id', $data['ids'])
            ->update(['status' => 'read', 'read_at' => now()]);
        return ['updated' => $count];
    }

    /**
     * @OA\Delete(
     *     path="/api/notifications/{notification}",
     *     tags={"Notifications"},
     *     summary="Delete a notification",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="notification",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Notification deleted")
     * )
     */
    public function destroy(Request $request, UserNotification $notification)
    {
        abort_unless($notification->owner_user_id === $request->user()->id, 403);
        $notification->delete();
        return response()->noContent();
    }
}
