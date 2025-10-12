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

class ContactController extends Controller
{
    /**
     * GET /contacts
     * Hỗ trợ:
     * - q: text search (name/email/phone/company) + hashtag trong q: #vip #design
     * - tag_ids: "1,2,3" hoặc [1,2,3]
     * - tags: "vip,design" hoặc "#vip,#design"
     * - tag_mode: any|all (mặc định any)
     * - without_tag: id hoặc name (loại trừ những contact đang có tag này)
     * - sort: name|-name|id|-id (mặc định -id)
     * - per_page: tối đa 100
     */
    public function index(Request $r)
    {
        $per = min(100, (int) $r->query('per_page', 20));

        $q = Contact::query()
            ->where('owner_user_id', $r->user()->id);

        // --- Parse q: tách hashtag ra khỏi phần text ---
        $rawQ         = trim((string) $r->query('q', ''));
        $hashTagNames = $this->extractHashtags($rawQ); // ['vip','design', ...]
        $textTerm     = $this->stripHashtags($rawQ);   // phần q không có #tag

        if ($textTerm !== '') {
            $term = "%{$textTerm}%";
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('company', 'like', $term);
            });
        }

        // --- tag_ids ---
        $tagIds = [];
        if ($tagIdsParam = $r->query('tag_ids')) {
            $tagIds = is_array($tagIdsParam) ? $tagIdsParam : explode(',', (string) $tagIdsParam);
            $tagIds = array_values(array_filter(array_map('intval', $tagIds)));
        }

        // --- tags (tên) ---
        $tagNames = [];
        if ($tagNamesParam = $r->query('tags')) {
            $tagNames = is_array($tagNamesParam) ? $tagNamesParam : explode(',', (string) $tagNamesParam);
            $tagNames = array_map(function ($s) {
                $s = trim((string) $s);
                return ltrim($s, '#');
            }, $tagNames);
            $tagNames = array_values(array_filter($tagNames, fn($s) => $s !== ''));
        }

        // Gộp hashtag trong q + tags param
        if (!empty($hashTagNames)) {
            $tagNames = array_values(array_unique(array_merge($tagNames, $hashTagNames)));
        }

        // --- Áp dụng filter theo tag_ids / tags (names) ---
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

        // --- WITHOUT TAG (id hoặc name) ---
        if ($r->filled('without_tag')) {
            $val = $r->query('without_tag');
            $q->whereDoesntHave('tags', function ($t) use ($val, $r) {
                if (is_numeric($val)) {
                    $t->where('tags.id', (int) $val);
                } else {
                    $t->where('tags.name', ltrim((string) $val, '#'));
                }
            });
        }

        // --- Sort ---
        $sort = (string) $r->query('sort', '-id');
        $sort === 'name'   ? $q->orderBy('name')
      : ($sort === '-name' ? $q->orderBy('name', 'desc')
      : ($sort === 'id'    ? $q->orderBy('id')
                           : $q->orderBy('id', 'desc')));

        return $q->with('tags')->paginate($per);
    }

    /**
     * POST /contacts
     * Nhận đầy đủ field theo migration/model.
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
            'source'          => 'nullable|string|max:50', // default 'manual' trong migration
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
        return ['data' => $contact->load('tags')];
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

        // Chặn vòng A↔B đơn giản
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
     * Body: { ids?: number[], names?: string[] }
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

        $ids = $body['ids'] ?? [];

        if (!empty($body['names'])) {
            foreach ($body['names'] as $name) {
                $name = ltrim(trim($name), '#');
                if ($name === '') continue;

                // tạo tag theo phạm vi user hiện tại
                $tag = Tag::firstOrCreate([
                    'owner_user_id' => $r->user()->id,
                    'name'          => $name,
                ]);
                $ids[] = $tag->id;
            }
        }

        if (!empty($ids)) {
            $contact->tags()->syncWithoutDetaching(array_unique($ids));
        }

        return $contact->fresh()->load('tags');
    }

    /**
     * DELETE /contacts/{contact}/tags/{tag}
     */
    public function detachTag(Request $r, Contact $contact, Tag $tag)
    {
        $this->authorizeOwner($r, $contact);
        $contact->tags()->detach($tag->id);
        return $contact->fresh()->load('tags');
    }

    /* ================== Helpers ================== */

    private function authorizeOwner(Request $r, Contact $c)
    {
        abort_unless($c->owner_user_id === $r->user()->id, 403);
    }

    /** Lấy danh sách hashtag (không kèm dấu #), lower-case */
    private function extractHashtags(string $q): array
    {
        if ($q === '') return [];
        preg_match_all('/#([\pL\pN_\-]+)/u', $q, $m);
        $names = $m[1] ?? [];
        $names = array_map(fn($s) => mb_strtolower($s), $names);
        return array_values(array_unique(array_filter($names, fn($s) => $s !== '')));
    }

    /** Loại bỏ phần #tag ra khỏi chuỗi query để còn lại text search */
    private function stripHashtags(string $q): string
    {
        if ($q === '') return '';
        $str = preg_replace('/#([\pL\pN_\-]+)/u', ' ', $q);
        return trim(preg_replace('/\s+/', ' ', (string) $str));
    }

    /**
     * GET /contacts/export?format=xlsx|csv + (q, tags, tag_ids, tag_mode, sort, per_page...) như index()
     * Trả về file Excel/CSV theo filter hiện tại
     */
    public function export(Request $r)
    {
        $query = Contact::query()
            ->where('owner_user_id', $r->user()->id)
            ->with('tags');

        // --- ids (tùy chọn) ---
        $ids = $r->input('ids', []);
        if (is_string($ids)) {
            $ids = array_filter(array_map('intval', explode(',', $ids)));
        }
        if (is_array($ids) && !empty($ids)) {
            $query->whereIn('id', $ids);
        }

        // --- các filter còn lại (q + hashtag trong q, tag_ids, tags, tag_mode, sort) y hệt index() ---
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

        // --- WITHOUT TAG (id hoặc name) cho export ---
        if ($r->filled('without_tag')) {
            $val = $r->query('without_tag');
            $query->whereDoesntHave('tags', function ($t) use ($val) {
                if (is_numeric($val)) {
                    $t->where('tags.id', (int) $val);
                } else {
                    $t->where('tags.name', ltrim((string)$val, '#'));
                }
            });
        }

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

    /**
     * GET /contacts/export-template
     */
    public function exportTemplate(Request $r)
    {
        $export = new ContactsTemplateExport();
        $format = strtolower((string)$r->query('format', 'xlsx'));

        return $format === 'csv'
            ? Excel::download($export, 'contacts_template.csv', \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, 'contacts_template.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * POST /contacts/import (multipart/form-data: file, match_by=id|email|phone)
     * Trả về thống kê created/updated/skipped + errors
     */
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

    public function bulkUpsert(Request $r)
    {
        $uid = $r->user()->id;
        $payload = $r->validate([
            'items' => ['required','array','min:1'],
            'match' => ['nullable', Rule::in(['id','email','phone','name_company'])],
        ]);
        $match = $payload['match'] ?? 'id';

        $created = 0; $updated = 0; $remCreated = 0; $remUpdated = 0; $results = [];

        DB::transaction(function () use ($uid, $payload, $match, &$created, &$updated, &$remCreated, &$remUpdated, &$results) {
            foreach ($payload['items'] as $row) {
                // Validate contact row
                $data = validator($row, [
                    'id'          => ['sometimes','integer'],
                    'name'        => ['required','string','max:255'],
                    'email'       => ['nullable','email','max:255'],
                    'phone'       => ['nullable','string','max:64'],
                    'company'     => ['nullable','string','max:255'],
                    'address'     => ['nullable','string'],
                    'notes'       => ['nullable','string'],
                    'job_title'   => ['nullable','string','max:255'],
                    'linkedin_url'=> ['nullable','url'],
                    'website_url' => ['nullable','url'],
                    'reminders'   => ['sometimes','array'],
                ])->validate();

                // Find target contact by match strategy
                $contact = null;
                if ($match === 'id' && !empty($data['id'])) {
                    $contact = Contact::where('owner_user_id',$uid)->find($data['id']);
                } elseif ($match === 'email' && !empty($data['email'])) {
                    $contact = Contact::where('owner_user_id',$uid)->where('email',$data['email'])->first();
                } elseif ($match === 'phone' && !empty($data['phone'])) {
                    $contact = Contact::where('owner_user_id',$uid)->where('phone',$data['phone'])->first();
                } elseif ($match === 'name_company' && !empty($data['name'])) {
                    $q = Contact::where('owner_user_id',$uid)->where('name',$data['name']);
                    if (!empty($data['company'])) $q->where('company',$data['company']);
                    $contact = $q->first();
                }

                $reminders = $data['reminders'] ?? [];
                unset($data['reminders'], $data['id']);

                if ($contact) {
                    $contact->fill($data)->save();
                    $updated++;
                } else {
                    $contact = new Contact($data);
                    $contact->owner_user_id = $uid;
                    $contact->save();
                    $created++;
                }

                // Upsert nested reminders
                foreach ($reminders as $rem) {
                    $remData = validator($rem, [
                        'id'      => ['sometimes','integer'],
                        'title'   => ['required','string','max:255'],
                        'note'    => ['nullable','string'],
                        'due_at'  => ['nullable','date'],
                        'status'  => ['nullable', Rule::in(['pending','done','skipped','cancelled'])],
                        'channel' => ['nullable', Rule::in(['app','email','calendar'])],
                    ])->validate();

                    $model = null;
                    if (!empty($remData['id'])) {
                        $model = Reminder::where('owner_user_id',$uid)
                            ->where('contact_id',$contact->id)
                            ->find($remData['id']);
                    }

                    $remData['owner_user_id'] = $uid;
                    $remData['contact_id']    = $contact->id;
                    if (!empty($remData['due_at'])) $remData['due_at'] = Carbon::parse($remData['due_at']);

                    if ($model) {
                        $model->fill($remData)->save();
                        $remUpdated++;
                    } else {
                        Reminder::create($remData);
                        $remCreated++;
                    }
                }

                $results[] = ['contact_id' => $contact->id];
            }
        });

        return response()->json(compact('created','updated','remCreated','remUpdated','results'));
    }

    /** POST /contacts/bulk-delete { "ids":[...] } */
    public function bulkDelete(Request $r)
    {
        $uid = $r->user()->id;
        $data = $r->validate(['ids'=>['required','array','min:1'],'ids.*'=>'integer']);
        $count = Contact::where('owner_user_id',$uid)->whereIn('id',$data['ids'])->delete();
        return response()->json(['deleted'=>$count]);
    }
}
