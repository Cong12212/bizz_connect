<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ContactsExport;
use App\Exports\ContactsTemplateExport;
use App\Imports\ContactsImport;
use App\Models\UserNotification;
use Carbon\Carbon;

class ContactController extends Controller
{
    /**
     * GET /contacts
     * Filters:
     *  - q (+hashtags #vip)
     *  - tag_ids, tags, tag_mode(any|all)
     *  - without_tag (id or name)
     *  - with_reminder / without_reminder (+status/after/before)
     *  - exclude_ids
     *  - sort: name|-name|id|-id (default -id)
     *  - per_page <= 100
     */
    public function index(Request $r)
    {
        $per = min(100, (int) $r->query('per_page', 20));

        $q = Contact::query()
            ->where('owner_user_id', $r->user()->id);

        // ===== q + hashtag =====
        $rawQ         = trim((string) $r->query('q', ''));
        $hashTagNames = $this->extractHashtags($rawQ);
        $textTerm     = $this->stripHashtags($rawQ);

        if ($textTerm !== '') {
            $term = "%{$textTerm}%";
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('phone', 'like', $term)
                  ->orWhere('company', 'like', $term);
            });
        }

        // ===== tag_ids / tags + mode =====
        $tagIds = [];
        if ($p = $r->query('tag_ids')) {
            $tagIds = is_array($p) ? $p : explode(',', (string)$p);
            $tagIds = array_values(array_filter(array_map('intval', $tagIds)));
        }

        $tagNames = [];
        if ($p = $r->query('tags')) {
            $tagNames = is_array($p) ? $p : explode(',', (string)$p);
            $tagNames = array_values(array_filter(array_map(fn($s)=>ltrim(trim($s),'#'), $tagNames)));
        }

        if (!empty($hashTagNames)) {
            $tagNames = array_values(array_unique(array_merge($tagNames, $hashTagNames)));
        }

        if (!empty($tagIds) || !empty($tagNames)) {
            $mode = $r->query('tag_mode', 'any'); // any|all
            if (!empty($tagIds)) {
                if ($mode === 'all') {
                    foreach ($tagIds as $id) {
                        $q->whereHas('tags', fn($t) => $t->where('tags.id', $id));
                    }
                } else {
                    $q->whereHas('tags', fn($t) => $t->whereIn('tags.id', $tagIds));
                }
            }
            if (!empty($tagNames)) {
                if ($mode === 'all') {
                    foreach ($tagNames as $name) {
                        $q->whereHas('tags', fn($t) => $t->where('tags.name', $name));
                    }
                } else {
                    $q->whereHas('tags', fn($t) => $t->whereIn('tags.name', $tagNames));
                }
            }
        }

        // ===== without_tag =====
        if ($r->filled('without_tag')) {
            $val = $r->query('without_tag');
            $q->whereDoesntHave('tags', function ($t) use ($val) {
                if (is_numeric($val)) {
                    $t->where('tags.id', (int)$val);
                } else {
                    $t->where('tags.name', ltrim((string)$val, '#'));
                }
            });
        }

        // ===== reminders filter (via pivot) =====
        if ($r->boolean('without_reminder')) {
            $status = $r->query('status');
            $after  = $r->query('after');
            $before = $r->query('before');

            $q->whereNotExists(function ($sub) use ($status, $after, $before) {
                $sub->selectRaw(1)
                    ->from('contact_reminder as cr')
                    ->join('reminders as r', 'r.id', '=', 'cr.reminder_id')
                    ->whereColumn('cr.contact_id', 'contacts.id')
                    ->whereColumn('r.owner_user_id', 'contacts.owner_user_id');

                if (!empty($status)) $sub->where('r.status', $status);
                if (!empty($after))  $sub->where('r.due_at', '>=', Carbon::parse($after));
                if (!empty($before)) $sub->where('r.due_at', '<=', Carbon::parse($before));
            });
        }

        if ($r->boolean('with_reminder')) {
            $status = $r->query('status');
            $after  = $r->query('after');
            $before = $r->query('before');

            $q->whereExists(function ($sub) use ($status, $after, $before) {
                $sub->selectRaw(1)
                    ->from('contact_reminder as cr')
                    ->join('reminders as r', 'r.id', '=', 'cr.reminder_id')
                    ->whereColumn('cr.contact_id', 'contacts.id')
                    ->whereColumn('r.owner_user_id', 'contacts.owner_user_id');

                if (!empty($status)) $sub->where('r.status', $status);
                if (!empty($after))  $sub->where('r.due_at', '>=', Carbon::parse($after));
                if (!empty($before)) $sub->where('r.due_at', '<=', Carbon::parse($before));
            });
        }

        // ===== exclude_ids =====
        if ($r->filled('exclude_ids')) {
            $ids = $r->query('exclude_ids');
            $ids = is_array($ids) ? $ids : explode(',', (string)$ids);
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!empty($ids)) {
                $q->whereNotIn('contacts.id', $ids);
            }
        }

        // ===== Sort =====
        $sort = (string) $r->query('sort', '-id');
        $sort === 'name'   ? $q->orderBy('name')
      : ($sort === '-name' ? $q->orderBy('name', 'desc')
      : ($sort === 'id'    ? $q->orderBy('id')
                           : $q->orderBy('id', 'desc')));

        // eager-load tags
        $q->with(['tags' => fn($t) => $t->where('tags.owner_user_id', $r->user()->id)]);

        return $q->paginate($per);
    }

    /**
     * POST /contacts
     */
    public function store(Request $r)
    {
        $data = $r->validate([
            'name'            => 'required|string|max:255',
            'company'         => 'nullable|string|max:255',
            'job_title'       => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'address'         => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'linkedin_url'    => 'nullable|url|max:255',
            'website_url'     => 'nullable|url|max:255',
            'ocr_raw'         => 'nullable|string',
            'duplicate_of_id' => [
                'nullable','integer',
                Rule::exists('contacts','id')->where(fn($q) => $q->where('owner_user_id', $r->user()->id)),
            ],
            'search_text'     => 'nullable|string',
            'source'          => 'nullable|string|max:50',
        ]);

        $data['owner_user_id'] = $r->user()->id;
        if (!isset($data['source']) || $data['source'] === null || $data['source'] === '') {
            $data['source'] = 'manual';
        }

        $c = Contact::create($data);

        UserNotification::log($r->user()->id, [
            'type'        => 'contact.created',
            'title'       => 'New contact created',
            'body'        => $c->name . ($c->company ? ' · '.$c->company : ''),
            'data'        => ['contact_id' => $c->id],
            'contact_id'  => $c->id,
        ]);

        return response()->json(['data' => $c->load('tags')], 201);
    }

    /**
     * GET /contacts/{contact}
     */
    public function show(Request $r, Contact $contact)
    {
        $this->authorizeOwner($r, $contact);
        $uid = $r->user()->id;
        $contact->load(['tags' => fn($t) => $t->where('tags.owner_user_id', $uid)]);
        return ['data' => $contact];
    }

    /**
     * PUT /contacts/{contact}
     */
    public function update(Request $r, Contact $contact)
    {
        $this->authorizeOwner($r, $contact);

        $payload = $r->validate([
            'name'            => 'sometimes|required|string|max:255',
            'company'         => 'sometimes|nullable|string|max:255',
            'job_title'       => 'sometimes|nullable|string|max:255',
            'email'           => 'sometimes|nullable|email|max:255',
            'phone'           => 'sometimes|nullable|string|max:50',
            'address'         => 'sometimes|nullable|string|max:255',
            'notes'           => 'sometimes|nullable|string',
            'linkedin_url'    => 'sometimes|nullable|url|max:255',
            'website_url'     => 'sometimes|nullable|url|max:255',
            'ocr_raw'         => 'sometimes|nullable|string',
            'duplicate_of_id' => [
                'sometimes','nullable','integer',
                Rule::exists('contacts','id')->where(fn($q) =>
                    $q->where('owner_user_id', $r->user()->id)
                ),
                Rule::notIn([$contact->id]),
            ],
            'search_text'     => 'sometimes|nullable|string',
            'source'          => 'sometimes|nullable|string|max:50',
        ]);

        // Prevent circular duplicate A↔B
        if (!empty($payload['duplicate_of_id'])) {
            $target = Contact::select('id','duplicate_of_id')
                ->where('owner_user_id', $r->user()->id)
                ->find($payload['duplicate_of_id']);
            if ($target && (int) $target->duplicate_of_id === (int) $contact->id) {
                return response()->json(['message' => 'Circular duplicate link (A↔B) is not allowed'], 422);
            }
        }

        $contact->fill($payload)->save();

        return ['data' => $contact->fresh()->load('tags')];
    }

    /**
     * DELETE /contacts/{contact}
     */
    public function destroy(Request $r, Contact $contact)
    {
        $this->authorizeOwner($r, $contact);
        $contact->delete();
        return response()->noContent();
    }

    /**
     * POST /contacts/{contact}/tags
     */
    public function attachTags(Request $r, Contact $contact)
    {
        $this->authorizeOwner($r, $contact);

        $body = $r->validate([
            'ids'     => 'sometimes|array',
            'ids.*'   => 'integer',
            'names'   => 'sometimes|array',
            'names.*' => 'string',
        ]);

        $ids = [];

        if (!empty($body['ids'])) {
            $ids = Tag::where('owner_user_id', $r->user()->id)
                ->whereIn('id', $body['ids'])
                ->pluck('id')->all();
        }

        if (!empty($body['names'])) {
            foreach ($body['names'] as $name) {
                $name = ltrim(trim($name), '#');
                if ($name === '') continue;

                $tag = Tag::firstOrCreate(
                    ['owner_user_id' => $r->user()->id, 'name' => $name],
                    []
                );
                $ids[] = $tag->id;
            }
        }

        if ($ids) {
            $contact->tags()->syncWithoutDetaching(array_unique($ids));
        }

        return $contact->fresh()->load(['tags' => fn($t) => $t->where('tags.owner_user_id', $r->user()->id)]);
    }

    /**
     * DELETE /contacts/{contact}/tags/{tag}
     */
    public function detachTag(Request $r, Contact $contact, Tag $tag)
    {
        $this->authorizeOwner($r, $contact);
        abort_unless($tag->owner_user_id === $r->user()->id, 403, 'Tag not owned by you');

        $contact->tags()->detach($tag->id);

        return $contact->fresh()->load(['tags' => fn($t) => $t->where('tags.owner_user_id', $r->user()->id)]);
    }

    /* ================== Helpers ================== */

    private function authorizeOwner(Request $r, Contact $c)
    {
        abort_unless($c->owner_user_id === $r->user()->id, 403);
    }

    private function extractHashtags(string $q): array
    {
        if ($q === '') return [];
        preg_match_all('/#([\pL\pN_\-]+)/u', $q, $m);
        $names = $m[1] ?? [];
        $names = array_map(fn($s) => mb_strtolower($s), $names);
        return array_values(array_unique(array_filter($names, fn($s) => $s !== '')));
    }

    private function stripHashtags(string $q): string
    {
        if ($q === '') return '';
        $str = preg_replace('/#([\pL\pN_\-]+)/u', ' ', $q);
        return trim(preg_replace('/\s+/', ' ', (string) $str));
    }

    /**
     * GET /contacts/export
     */
    public function export(Request $r)
    {
        $query = Contact::query()
            ->where('owner_user_id', $r->user()->id)
            ->with(['tags' => fn($t) => $t->where('tags.owner_user_id', $r->user()->id)]);

        // ids (optional)
        $ids = $r->input('ids', []);
        if (is_string($ids)) $ids = array_filter(array_map('intval', explode(',', $ids)));
        if (is_array($ids) && !empty($ids)) $query->whereIn('id', $ids);

        // q + hashtags
        $rawQ         = trim((string) $r->query('q', ''));
        $hashTagNames = $this->extractHashtags($rawQ);
        $textTerm     = $this->stripHashtags($rawQ);

        if ($textTerm !== '') {
            $term = "%{$textTerm}%";
            $query->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('phone', 'like', $term)
                  ->orWhere('company', 'like', $term);
            });
        }

        // tags
        $tagIds = [];
        if ($p = $r->query('tag_ids')) {
            $tagIds = is_array($p) ? $p : explode(',', (string)$p);
            $tagIds = array_values(array_filter(array_map('intval', $tagIds)));
        }

        $tagNames = [];
        if ($p = $r->query('tags')) {
            $tagNames = is_array($p) ? $p : explode(',', (string)$p);
            $tagNames = array_values(array_filter(array_map(fn($s)=>ltrim(trim($s),'#'), $tagNames)));
        }

        if (!empty($hashTagNames)) {
            $tagNames = array_values(array_unique(array_merge($tagNames, $hashTagNames)));
        }

        if (!empty($tagIds) || !empty($tagNames)) {
            $mode = $r->query('tag_mode', 'any');
            if (!empty($tagIds)) {
                if ($mode === 'all') {
                    foreach ($tagIds as $id) {
                        $query->whereHas('tags', fn($t) => $t->where('tags.id', $id));
                    }
                } else {
                    $query->whereHas('tags', fn($t) => $t->whereIn('tags.id', $tagIds));
                }
            }
            if (!empty($tagNames)) {
                if ($mode === 'all') {
                    foreach ($tagNames as $name) {
                        $query->whereHas('tags', fn($t) => $t->where('tags.name', $name));
                    }
                } else {
                    $query->whereHas('tags', fn($t) => $t->whereIn('tags.name', $tagNames));
                }
            }
        }

        // without_tag
        if ($r->filled('without_tag')) {
            $val = $r->query('without_tag');
            $query->whereDoesntHave('tags', function ($t) use ($val) {
                if (is_numeric($val)) $t->where('tags.id', (int) $val);
                else $t->where('tags.name', ltrim((string)$val, '#'));
            });
        }

        // exclude_ids
        if ($r->filled('exclude_ids')) {
            $eids = $r->query('exclude_ids');
            $eids = is_array($eids) ? $eids : explode(',', (string)$eids);
            $eids = array_values(array_filter(array_map('intval', $eids)));
            if (!empty($eids)) $query->whereNotIn('contacts.id', $eids);
        }

        // reminders filter (pivot)
        if ($r->boolean('without_reminder')) {
            $status = $r->query('status');
            $after  = $r->query('after');
            $before = $r->query('before');

            $query->whereNotExists(function ($sub) use ($status, $after, $before) {
                $sub->selectRaw(1)
                    ->from('contact_reminder as cr')
                    ->join('reminders as r', 'r.id', '=', 'cr.reminder_id')
                    ->whereColumn('cr.contact_id', 'contacts.id')
                    ->whereColumn('r.owner_user_id', 'contacts.owner_user_id');

                if (!empty($status)) $sub->where('r.status', $status);
                if (!empty($after))  $sub->where('r.due_at', '>=', Carbon::parse($after));
                if (!empty($before)) $sub->where('r.due_at', '<=', Carbon::parse($before));
            });
        }

        if ($r->boolean('with_reminder')) {
            $status = $r->query('status');
            $after  = $r->query('after');
            $before = $r->query('before');

            $query->whereExists(function ($sub) use ($status, $after, $before) {
                $sub->selectRaw(1)
                    ->from('contact_reminder as cr')
                    ->join('reminders as r', 'r.id', '=', 'cr.reminder_id')
                    ->whereColumn('cr.contact_id', 'contacts.id')
                    ->whereColumn('r.owner_user_id', 'contacts.owner_user_id');

                if (!empty($status)) $sub->where('r.status', $status);
                if (!empty($after))  $sub->where('r.due_at', '>=', Carbon::parse($after));
                if (!empty($before)) $sub->where('r.due_at', '<=', Carbon::parse($before));
            });
        }

        // sort
        $sort = (string)$r->query('sort', '-id');
        $sort === 'name'   ? $query->orderBy('name')
      : ($sort === '-name' ? $query->orderBy('name', 'desc')
      : ($sort === 'id'    ? $query->orderBy('id')
                           : $query->orderBy('id', 'desc')));

        $contacts = $query->get();

        $export  = new ContactsExport($contacts);
        $format  = strtolower((string)$r->query('format', 'xlsx'));
        $file    = 'contacts_'.now()->format('Ymd_His').'.'.$format;

        return $format === 'csv'
            ? Excel::download($export, $file, \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, $file, \Maatwebsite\Excel\Excel::XLSX);
    }

    /** POST /contacts/import … (giữ nguyên phần còn lại) */
    public function import(Request $r)
    {
        $data = $r->validate([
            'file'     => 'required|file|mimes:xlsx,csv',
            'match_by' => 'nullable|in:id,email,phone',
        ]);

        $matchBy = $data['match_by'] ?? 'id';

        $import = new ContactsImport($r->user()->id, $matchBy);
        Excel::import($import, $data['file']);

        return response()->json([
            'status'  => 'ok',
            'summary' => $import->result(),
        ]);
    }

    public function bulkDelete(Request $r)
    {
        $uid = $r->user()->id;
        $data = $r->validate(['ids'=>['required','array','min:1'],'ids.*'=>'integer']);
        $count = Contact::where('owner_user_id',$uid)->whereIn('id',$data['ids'])->delete();
        return response()->json(['deleted'=>$count]);
    }
}
