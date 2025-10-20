<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/notifications",
     *     tags={"Notifications"},
     *     summary="Get user notifications with filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="scope",
     *         in="query",
     *         description="Filter scope",
     *         @OA\Schema(type="string", enum={"all", "unread", "upcoming", "past"}, default="all")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of results (max 20)",
     *         @OA\Schema(type="integer", default=20, maximum=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of notifications",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/UserNotification")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $r)
    {
        $uid = $r->user()->id;
        $limit = min(20, (int)$r->query('limit', 20));
        $scope = $r->query('scope', 'all');

        $q = UserNotification::where('owner_user_id', $uid);

        if ($scope === 'unread') {
            $q->where('status', 'unread');
        } elseif ($scope === 'upcoming') {
            $q->whereNotNull('scheduled_at')->where('scheduled_at', '>=', now());
        } elseif ($scope === 'past') {
            $q->where(function($w){
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
     *     path="/api/notifications/{id}/read",
     *     tags={"Notifications"},
     *     summary="Mark notification as read",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as read",
     *         @OA\JsonContent(ref="#/components/schemas/UserNotification")
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not notification owner"),
     *     @OA\Response(response=404, description="Notification not found")
     * )
     */
    public function markRead(Request $r, UserNotification $notification)
    {
        abort_unless($notification->owner_user_id === $r->user()->id, 403);
        if ($notification->status === 'unread') {
            $notification->update(['status' => 'read', 'read_at' => now()]);
        }
        return $notification;
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/{id}/done",
     *     tags={"Notifications"},
     *     summary="Mark notification as done",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as done",
     *         @OA\JsonContent(ref="#/components/schemas/UserNotification")
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not notification owner"),
     *     @OA\Response(response=404, description="Notification not found")
     * )
     */
    public function markDone(Request $r, UserNotification $notification)
    {
        abort_unless($notification->owner_user_id === $r->user()->id, 403);
        $notification->update(['status' => 'done', 'read_at' => now()]);
        return $notification;
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/bulk-read",
     *     tags={"Notifications"},
     *     summary="Bulk mark notifications as read",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 description="Array of notification IDs to mark as read"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="updated", type="integer", description="Number of notifications updated")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bulkRead(Request $r)
    {
        $data = $r->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $uid = $r->user()->id;
        $count = UserNotification::where('owner_user_id', $uid)
            ->whereIn('id', $data['ids'])
            ->update(['status' => 'read', 'read_at' => now()]);
        return ['updated' => $count];
    }

    /**
     * @OA\Delete(
     *     path="/api/notifications/{id}",
     *     tags={"Notifications"},
     *     summary="Delete a notification",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Notification deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden - Not notification owner"),
     *     @OA\Response(response=404, description="Notification not found")
     * )
     */
    public function destroy(Request $r, UserNotification $notification)
    {
        abort_unless($notification->owner_user_id === $r->user()->id, 403);
        $notification->delete();
        return response()->noContent();
    }
}
