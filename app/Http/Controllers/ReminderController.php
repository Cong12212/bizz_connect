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
    /** List + filter (hỗ trợ lọc theo contact qua contact_id HOẶC pivot) */
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
        if ($r->boolean('overdue')) $q->where('status','pending')->whereNotNull('due_at')->where('due_at','<', now());

        if ($r->boolean('with_contacts')) $q->with('contacts');

        return $q->orderBy('due_at')->paginate(min(100, (int)$r->query('per_page', 20)));
    }

    public function show(Request $r, Reminder $reminder)
    {
        abort_unless($reminder->owner_user_id === $r->user()->id, 403);
        return $reminder->load('contacts');
    }

    /** CREATE: nhận contact_ids[] (hoặc contact_id) -> đặt primary + sync pivot */
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
                'contact_id' => $ids->first(), // contact chính
            ]);           

            $rem->contacts()->syncWithoutDetaching($ids->all());

            UserNotification::log($uid, [
    'type'  => 'reminder.created',
    'title' => 'New reminder',
    'body'  => $rem->title,
    'data'  => ['reminder_id' => $rem->id, 'contact_ids' => $ids->all()],
    'reminder_id' => $rem->id,
    'scheduled_at' => $rem->due_at, // để sort gần due hơn
]);

            return response()->json($rem->load('contacts'), 201);
        });
    }

    /** UPDATE: cho phép thay toàn bộ danh sách contacts (pivot) + đổi primary */
  public function update(Request $r, Reminder $reminder)
{
    abort_unless($reminder->owner_user_id === $r->user()->id, 403);
    $uid  = $r->user()->id; // 👈 lấy trước

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

    return DB::transaction(function () use ($reminder, $data, $uid) { // 👈 thêm $uid
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
            \App\Models\UserNotification::log($uid, [ // 👈 dùng $uid
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
        // Lấy cả record chưa xoá và đã soft-delete
        $found = Reminder::withTrashed()
            ->where('owner_user_id', $uid)
            ->whereIn('id', $ids)
            ->pluck('id');

        if ($found->isEmpty()) {
            return response()->json(['deleted' => 0, 'found' => []]);
        }

        // Dọn pivot trước (an toàn cho cả record đã soft-delete từ trước)
        DB::table('contact_reminder')->whereIn('reminder_id', $found)->delete();

        // Soft-delete những record chưa xoá
        $deleted = Reminder::whereIn('id', $found)->delete();

        return response()->json([
            'deleted' => (int) $deleted,   // số record mới bị soft-delete trong call này
            'found'   => $found->values(), // các ID hợp lệ tìm thấy (kể cả đã xoá từ trước)
        ]);
    });
}



    /** Gắn thêm contacts cho 1 reminder (không xoá cái đang có) */
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

    /** Bỏ 1 contact ra khỏi reminder; nếu là contact chính thì chuyển primary sang contact khác trong pivot */
    public function detachContact(Request $r, Reminder $reminder, int $contact)
    {
        abort_unless($reminder->owner_user_id === $r->user()->id, 403);

        return DB::transaction(function () use ($reminder, $contact) {
            $reminder->contacts()->detach($contact);

            if ((int)$reminder->contact_id === (int)$contact) {
                $next = $reminder->contacts()->orderBy('contacts.id')->first();
                $reminder->contact_id = $next?->id; // có thể null nếu không còn ai
                $reminder->save();
            }
            return ['ok' => true];
        });
    }

    /** Reminders của 1 contact: match primary hoặc nằm trong pivot */
    public function byContact(Request $r, Contact $contact)
    {
        abort_unless($contact->owner_user_id === $r->user()->id, 403);
        $uid = $r->user()->id;

        $q = Reminder::where('owner_user_id', $uid)
            ->where(function($w) use ($contact) {
                $w->where('contact_id', $contact->id)
                  ->orWhereHas('contacts', fn($t) => $t->where('contacts.id', $contact->id));
            });

        return $q->orderBy('due_at')->paginate(min(100, (int)$r->query('per_page', 50)));
    }

    public function pivotIndex(Request $r)
{
    $uid = $r->user()->id;
    $per = min(100, (int)$r->query('per_page', 20));

    $q = DB::table('contact_reminder as cr')
        ->join('reminders as r', 'r.id', '=', 'cr.reminder_id')
        ->join('contacts  as c', 'c.id', '=', 'cr.contact_id')
        ->where('r.owner_user_id', $uid);

    if ($r->filled('status'))     $q->where('r.status', $r->query('status'));
    if ($r->filled('contact_id')) $q->where('cr.contact_id', (int)$r->query('contact_id'));
    if ($r->filled('after'))      $q->where('r.due_at', '>=', $r->query('after'));
    if ($r->filled('before'))     $q->where('r.due_at', '<=', $r->query('before'));
    if ($r->boolean('overdue')) {
        $q->whereNotNull('r.due_at')
          ->where('r.due_at', '<', now())
          ->where('r.status', '!=', 'done');
    }

   $q->selectRaw("
       CONCAT(r.id, ':', cr.contact_id)      as edge_key,
       r.id                                  as reminder_id,
    cr.contact_id                          as contact_id,
      r.title,
      r.note,
       DATE_FORMAT(
           CONVERT_TZ(r.due_at, @@session.time_zone, '+00:00'),
           '%Y-%m-%dT%H:%i:%sZ'
       )                                      as due_at,
       r.status,
       r.channel,
       (r.contact_id = cr.contact_id)         as is_primary,
       c.name                                 as contact_name,
       c.company                              as contact_company
   ");

    // null due_at xuống cuối
    $q->orderByRaw('r.due_at is null asc, r.due_at asc, r.id desc');

    return $q->paginate($per);
}
}
