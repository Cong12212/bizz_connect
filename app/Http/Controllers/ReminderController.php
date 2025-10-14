<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\UserNotification;

class ReminderController extends Controller
{
    /**
     * Helper: parse per_page từ query (hỗ trợ cả "20/page"), giới hạn max 100.
     */
    private function parsePerPage(Request $r, int $default = 20): int
    {
        $perRaw = (string) $r->query('per_page', (string) $default);
        preg_match('/\d+/', $perRaw, $m);
        $per = (int) ($m[0] ?? $default);
        if ($per <= 0) $per = $default;
        return min(100, $per);
    }

    /** List + filter (supports filtering by contact via contact_id OR pivot) */
    public function index(Request $r)
    {
        $uid = $r->user()->id;
        $q = Reminder::query()->where('owner_user_id', $uid);

        if ($cid = $r->query('contact_id')) {
            $q->where(function($w) use ($cid) {
                $w->where('contact_id', (int)$cid)
                  ->orWhereHas('contacts', fn($t) => $t->where('contacts.id', (int)$cid));
            });
        }
        if ($r->filled('status')) $q->where('status', $r->query('status'));
        if ($r->filled('before')) $q->where('due_at', '<=', Carbon::parse($r->query('before')));
        if ($r->filled('after'))  $q->where('due_at', '>=', Carbon::parse($r->query('after')));
        if ($r->boolean('overdue')) {
            $q->where('status','pending')
              ->whereNotNull('due_at')
              ->where('due_at','<', now());
        }

        if ($r->boolean('with_contacts')) $q->with('contacts');

        $per = $this->parsePerPage($r, 20);
        return $q->orderBy('due_at')->paginate($per);
    }

    public function show(Request $r, Reminder $reminder)
    {
        abort_unless($reminder->owner_user_id === $r->user()->id, 403);
        return $reminder->load('contacts');
    }

    /** CREATE: accepts contact_ids[] (or contact_id) -> set primary + sync pivot */
    public function store(Request $r)
    {
        $uid  = $r->user()->id;
        $data = $r->validate([
            'title'   => 'required|string|max:255',
            'note'    => 'nullable|string',
            'due_at'  => 'required|date',
            'status'  => ['nullable', Rule::in(['pending','done','skipped','cancelled'])],
            'channel' => ['nullable', Rule::in(['app','email','calendar'])],

            'contact_id'  => [
                'sometimes','integer',
                Rule::exists('contacts','id')->where(fn($q)=>$q->where('owner_user_id',$uid)),
            ],
            'contact_ids' => ['sometimes','array','min:1'],
            'contact_ids.*' => [
                'integer',
                Rule::exists('contacts','id')->where(fn($q)=>$q->where('owner_user_id',$uid)),
            ],
        ]);

        $ids = collect($data['contact_ids'] ?? [])
            ->when(isset($data['contact_id']), fn($c)=>$c->prepend((int)$data['contact_id']))
            ->unique()->filter()->values();

        if ($ids->isEmpty()) {
            return response()->json(['message' => 'contact_ids or contact_id is required'], 422);
        }

        return DB::transaction(function () use ($uid, $data, $ids) {
            $rem = Reminder::create([
                'owner_user_id' => $uid,
                'title'   => $data['title'],
                'note'    => $data['note'] ?? null,
                'due_at'  => Carbon::parse($data['due_at']),
                'status'  => $data['status'] ?? 'pending',
                'channel' => $data['channel'] ?? 'app',
                'contact_id' => $ids->first(), // primary contact
            ]);

            $rem->contacts()->syncWithoutDetaching($ids->all());

            UserNotification::log($uid, [
                'type'  => 'reminder.created',
                'title' => 'New reminder',
                'body'  => $rem->title,
                'data'  => ['reminder_id' => $rem->id, 'contact_ids' => $ids->all()],
                'reminder_id' => $rem->id,
                'scheduled_at' => $rem->due_at,
            ]);

            return response()->json($rem->load('contacts'), 201);
        });
    }

    /** UPDATE: allows replacing entire contacts list (pivot) + change primary */
    public function update(Request $r, Reminder $reminder)
    {
        abort_unless($reminder->owner_user_id === $r->user()->id, 403);
        $uid  = $r->user()->id;

        $data = $r->validate([
            'title'   => 'sometimes|string|max:255',
            'note'    => 'sometimes|nullable|string',
            'due_at'  => 'sometimes|nullable|date',
            'status'  => ['sometimes', Rule::in(['pending','done','skipped','cancelled'])],
            'channel' => ['sometimes', Rule::in(['app','email','calendar'])],
            'contact_id'  => ['sometimes','integer', Rule::exists('contacts','id')->where(fn($q)=>$q->where('owner_user_id',$uid))],
            'contact_ids' => ['sometimes','array','min:1'],
            'contact_ids.*' => ['integer', Rule::exists('contacts','id')->where(fn($q)=>$q->where('owner_user_id',$uid))],
        ]);

        return DB::transaction(function () use ($reminder, $data, $uid) {
            if (array_key_exists('due_at',$data)) {
                $data['due_at'] = $data['due_at'] ? \Carbon\Carbon::parse($data['due_at']) : null;
            }

            $reminder->fill($data);

            if (array_key_exists('contact_ids', $data) || array_key_exists('contact_id', $data)) {
                $ids = collect($data['contact_ids'] ?? [])
                    ->when(isset($data['contact_id']), fn($c)=>$c->prepend((int)$data['contact_id']))
                    ->unique()->filter()->values();

                if ($ids->isNotEmpty()) {
                    $reminder->contact_id = $ids->first();
                    $reminder->save();
                    $reminder->contacts()->sync($ids->all());
                } else {
                    $reminder->save();
                }
            } else {
                $reminder->save();
            }

            if (($data['status'] ?? null) === 'done') {
                \App\Models\UserNotification::log($uid, [
                    'type'        => 'reminder.done',
                    'title'       => 'Reminder completed',
                    'body'        => $reminder->title,
                    'data'        => ['reminder_id' => $reminder->id],
                    'reminder_id' => $reminder->id,
                ]);
            }

            return $reminder->load('contacts');
        });
    }

    public function destroy(Request $r, Reminder $reminder)
    {
        abort_unless($reminder->owner_user_id === $r->user()->id, 403);
        $reminder->delete();
        return response()->noContent();
    }

    public function markDone(Request $r, Reminder $reminder)
    {
        abort_unless($reminder->owner_user_id === $r->user()->id, 403);
        $reminder->update(['status' => 'done']);
        return $reminder->fresh()->load('contacts');
    }

    /** BULK: status */
    public function bulkStatus(Request $r)
    {
        $uid = $r->user()->id;
        $data = $r->validate([
            'ids' => ['required','array','min:1'], 'ids.*' => ['integer'],
            'status' => ['required', Rule::in(['pending','done','skipped','cancelled'])],
        ]);
        $count = Reminder::where('owner_user_id',$uid)->whereIn('id',$data['ids'])
            ->update(['status'=>$data['status']]);
        return ['updated'=>$count];
    }

    /** BULK: delete */
    public function bulkDelete(Request $r)
    {
        $uid = $r->user()->id;

        $ids = collect($r->input('ids', []))
            ->map(fn($v) => (int) $v)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return response()->json(['deleted' => 0, 'found' => []]);
        }

        return DB::transaction(function () use ($ids, $uid) {
            $found = Reminder::withTrashed()
                ->where('owner_user_id', $uid)
                ->whereIn('id', $ids)
                ->pluck('id');

            if ($found->isEmpty()) {
                return response()->json(['deleted' => 0, 'found' => []]);
            }

            DB::table('contact_reminder')->whereIn('reminder_id', $found)->delete();

            $deleted = Reminder::whereIn('id', $found)->delete();

            return response()->json([
                'deleted' => (int) $deleted,
                'found'   => $found->values(),
            ]);
        });
    }

    /** Attach additional contacts to a reminder (without removing existing ones) */
    public function attachContacts(Request $r, Reminder $reminder)
    {
        abort_unless($reminder->owner_user_id === $r->user()->id, 403);
        $uid = $r->user()->id;

        $payload = $r->validate([
            'ids'   => ['required','array','min:1'],
            'ids.*' => [
                'integer',
                Rule::exists('contacts','id')->where(fn($q)=>$q->where('owner_user_id',$uid)),
            ],
            'set_primary' => ['sometimes','boolean'],
        ]);

        return DB::transaction(function () use ($reminder, $payload) {
            $ids = collect($payload['ids'])->unique()->values();
            $reminder->contacts()->syncWithoutDetaching($ids->all());
            if (!empty($payload['set_primary'])) {
                $reminder->contact_id = $ids->first();
                $reminder->save();
            }
            return $reminder->load('contacts');
        });
    }

    /** Detach a contact from reminder; if it's the primary contact, switch primary to another contact in pivot */
    public function detachContact(Request $r, Reminder $reminder, int $contact)
    {
        abort_unless($reminder->owner_user_id === $r->user()->id, 403);

        return DB::transaction(function () use ($reminder, $contact) {
            $reminder->contacts()->detach($contact);

            if ((int)$reminder->contact_id === (int)$contact) {
                $next = $reminder->contacts()->orderBy('contacts.id')->first();
                $reminder->contact_id = $next?->id; // can be null if no one left
                $reminder->save();
            }
            return ['ok' => true];
        });
    }

    /** Reminders for a contact: match primary or in pivot */
    public function byContact(Request $r, Contact $contact)
    {
        abort_unless($contact->owner_user_id === $r->user()->id, 403);
        $uid = $r->user()->id;

        $q = Reminder::where('owner_user_id', $uid)
            ->where(function($w) use ($contact) {
                $w->where('contact_id', $contact->id)
                  ->orWhereHas('contacts', fn($t) => $t->where('contacts.id', $contact->id));
            });

        $per = $this->parsePerPage($r, 50);
        return $q->orderBy('due_at')->paginate($per);
    }

   public function pivotIndex(Request $r)
{
    $uid = $r->user()->id;

    // per_page: chấp nhận "20" hoặc "20/page"
    $perRaw = (string) $r->query('per_page', '20');
    preg_match('/\d+/', $perRaw, $m);
    $per  = max(1, min(100, (int)($m[0] ?? 20)));
    $page = max(1, (int)$r->query('page', 1));

    // ===== Base query (chung các filter) – KHÔNG select cột ở đây =====
    $base = \DB::table('contact_reminder as cr')
        ->join('reminders as r', 'r.id', '=', 'cr.reminder_id')
        ->join('contacts  as c', 'c.id', '=', 'cr.contact_id')
        ->where('r.owner_user_id', $uid);

    if ($r->filled('status'))     $base->where('r.status', $r->query('status'));
    if ($r->filled('contact_id')) $base->where('cr.contact_id', (int)$r->query('contact_id'));
    if ($r->filled('after'))      $base->where('r.due_at', '>=', $r->query('after'));
    if ($r->filled('before'))     $base->where('r.due_at', '<=', $r->query('before'));
    if ($r->boolean('overdue')) {
        $base->whereNotNull('r.due_at')
             ->where('r.due_at', '<', now())
             ->where('r.status', '!=', 'done');
    }

    // ===== 1) Lấy danh sách DISTINCT reminder_id theo thứ tự sort =====
    // MySQL: dùng subquery group-by để vừa distinct vừa giữ order mong muốn
    $distinctRem = (clone $base)
        ->select('r.id as reminder_id', 'r.due_at')
        ->groupBy('r.id', 'r.due_at');

    // Tổng số reminder sau khi lọc
    $total = \DB::query()->fromSub($distinctRem, 't')->count();

    // forPage = OFFSET/LIMIT theo trang
    $orderExpr = 't.due_at is null asc, t.due_at asc, t.reminder_id desc';
    $ids = \DB::query()
        ->fromSub($distinctRem, 't')
        ->orderByRaw($orderExpr)
        ->forPage($page, $per)
        ->pluck('reminder_id')
        ->all();

    // Nếu trang không có gì
    if (empty($ids)) {
        return response()->json([
            'data'         => [],
            'total'        => $total,
            'per_page'     => $per,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $per),
        ]);
    }

    // ===== 2) Lấy toàn bộ EDGES của các reminder_id trong trang đó =====
    $rows = (clone $base)
        ->whereIn('r.id', $ids)
        ->selectRaw("
            CONCAT(r.id, ':', cr.contact_id)      as edge_key,
            r.id                                  as reminder_id,
            cr.contact_id                         as contact_id,
            r.title,
            r.note,
            DATE_FORMAT(
                CONVERT_TZ(r.due_at, @@session.time_zone, '+00:00'),
                '%Y-%m-%dT%H:%i:%sZ'
            )                                     as due_at,
            r.status,
            r.channel,
            (r.contact_id = cr.contact_id)        as is_primary,
            c.name                                as contact_name,
            c.company                             as contact_company
        ")
        // Thứ tự: theo order của reminder, rồi theo contact để hiển thị ổn định
        ->orderByRaw('r.due_at is null asc, r.due_at asc, r.id desc')
        ->orderBy('cr.contact_id')
        ->get();

    return response()->json([
        'data'         => $rows,
        'total'        => $total,                   // tổng SỐ REMINDER
        'per_page'     => $per,
        'current_page' => $page,
        'last_page'    => (int) ceil($total / $per),
    ]);
}

}
