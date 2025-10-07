<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    /**
     * GET /contacts
     * Hỗ trợ:
     * - q: text search (name/email/phone/company) + hashtag trong q: #vip #design
     * - tag_ids: "1,2,3" hoặc [1,2,3]
     * - tags: "vip,design" hoặc "#vip,#design"
     * - tag_mode: any|all (mặc định any)
     * - sort: name|-name|id|-id (mặc định -id)
     * - per_page: tối đa 100
     */
    public function index(Request $r)
    {
        $per = min(100, (int) $r->query('per_page', 20));

        $q = Contact::query()->where('owner_user_id', $r->user()->id);

        // --- Parse q: tách hashtag ra khỏi phần text ---
        $rawQ = trim((string) $r->query('q', ''));
        $hashTagNames = $this->extractHashtags($rawQ); // ['vip','design', ...]
        $textTerm = $this->stripHashtags($rawQ);       // phần q không có #tag

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
     * Tất cả field đều "sometimes|..." để hỗ trợ partial update.
     * Chặn duplicate_of_id tự tham chiếu và chỉ cho phép trỏ tới contact cùng owner.
     * (kèm chặn vòng A↔B đơn giản)
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
                Rule::notIn([$contact->id]), // không được trỏ tới chính nó
            ],
            'search_text'     => 'sometimes|nullable|string',
            'source'          => 'sometimes|nullable|string|max:50',
        ]);

        // Chặn vòng A↔B đơn giản (tuỳ chọn)
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
                $tag = Tag::firstOrCreate(['name' => $name]);
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
        preg_match_all('/#([\\pL\\pN_\\-]+)/u', $q, $m);
        $names = $m[1] ?? [];
        $names = array_map(fn($s) => mb_strtolower($s), $names);
        return array_values(array_unique(array_filter($names, fn($s) => $s !== '')));
    }

    /** Loại bỏ phần #tag ra khỏi chuỗi query để còn lại text search */
    private function stripHashtags(string $q): string
    {
        if ($q === '') return '';
        $str = preg_replace('/#([\\pL\\pN_\\-]+)/u', ' ', $q);
        return trim(preg_replace('/\\s+/', ' ', (string) $str));
    }
}
