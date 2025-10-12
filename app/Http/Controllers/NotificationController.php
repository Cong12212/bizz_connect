<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    // GET /notifications?scope=all|unread|upcoming|past&limit=20
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

    // POST /notifications/{id}/read
    public function markRead(Request $r, UserNotification $notification)
    {
        abort_unless($notification->owner_user_id === $r->user()->id, 403);
        if ($notification->status === 'unread') {
            $notification->update(['status' => 'read', 'read_at' => now()]);
        }
        return $notification;
    }

    // POST /notifications/{id}/done
    public function markDone(Request $r, UserNotification $notification)
    {
        abort_unless($notification->owner_user_id === $r->user()->id, 403);
        $notification->update(['status' => 'done', 'read_at' => now()]);
        return $notification;
    }

    // POST /notifications/bulk-read { ids: number[] }
    public function bulkRead(Request $r)
    {
        $data = $r->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $uid = $r->user()->id;
        $count = UserNotification::where('owner_user_id', $uid)
            ->whereIn('id', $data['ids'])
            ->update(['status' => 'read', 'read_at' => now()]);
        return ['updated' => $count];
    }

    // DELETE /notifications/{id}
    public function destroy(Request $r, UserNotification $notification)
    {
        abort_unless($notification->owner_user_id === $r->user()->id, 403);
        $notification->delete();
        return response()->noContent();
    }
}
