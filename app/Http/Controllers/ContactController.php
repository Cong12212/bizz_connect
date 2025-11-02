<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Address;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * @OA\Get(
     *   path="/api/contacts",
     *   tags={"Contacts"},
     *   summary="List contacts",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="tag", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="Contacts list",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="data", type="array", @OA\Items(
     *         type="object",
     *         @OA\Property(property="id", type="integer"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string", nullable=true),
     *         @OA\Property(property="phone", type="string", nullable=true),
     *         @OA\Property(property="company", type="string", nullable=true),
     *         @OA\Property(property="job_title", type="string", nullable=true)
     *       )),
     *       @OA\Property(property="current_page", type="integer"),
     *       @OA\Property(property="total", type="integer"),
     *       @OA\Property(property="per_page", type="integer")
     *     )
     *   )
     * )
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
            $tagNames = array_values(array_filter(array_map(fn($s) => ltrim(trim($s), '#'), $tagNames)));
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

        // eager-load tags and address with relations
        $q->with([
            'tags' => fn($t) => $t->where('tags.owner_user_id', $r->user()->id),
            'address.city',
            'address.state',
            'address.country'
        ]);

        return $q->paginate($per);
    }

    /**
     * @OA\Post(
     *     path="/api/contacts",
     *     tags={"Contacts"},
     *     summary="Create a new contact",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="John Doe"),
     *             @OA\Property(property="company", type="string", maxLength=255, example="ABC Corp"),
     *             @OA\Property(property="job_title", type="string", maxLength=255, example="CEO"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255, example="john@example.com"),
     *             @OA\Property(property="phone", type="string", maxLength=50, example="+1234567890"),
     *             @OA\Property(property="address_detail", type="string", maxLength=255),
     *             @OA\Property(property="city", type="string", maxLength=20),
     *             @OA\Property(property="state", type="string", maxLength=20),
     *             @OA\Property(property="country", type="string", maxLength=10),
     *             @OA\Property(property="notes", type="string", example="Important client"),
     *             @OA\Property(property="linkedin_url", type="string", format="url", maxLength=255),
     *             @OA\Property(property="website_url", type="string", format="url", maxLength=255)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Contact created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", nullable=true),
     *                 @OA\Property(property="phone", type="string", nullable=true),
     *                 @OA\Property(property="company", type="string", nullable=true),
     *                 @OA\Property(property="job_title", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address_detail' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
            'linkedin_url' => 'nullable|url|max:255',
            'website_url' => 'nullable|url|max:255',
            'ocr_raw' => 'nullable|string',
            'duplicate_of_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where(fn($q) => $q->where('owner_user_id', $request->user()->id))],
            'search_text' => 'nullable|string',
            'source' => 'nullable|string|max:50',
        ]);

        // Tạo address nếu có thông tin
        $addressId = null;
        if ($request->filled('address_detail') || $request->filled('city')) {
            // Lấy ID từ code
            $cityId = $request->city ? DB::table('cities')->where('code', $request->city)->value('id') : null;
            $stateId = $request->state ? DB::table('states')->where('code', $request->state)->value('id') : null;
            $countryId = $request->country ? DB::table('countries')->where('code', $request->country)->value('id') : null;

            $address = Address::create([
                'address_detail' => $request->address_detail,
                'city_id' => $cityId,
                'state_id' => $stateId,
                'country_id' => $countryId,
            ]);
            $addressId = $address->id;
        }

        $contact = Contact::create([
            'owner_user_id' => $request->user()->id,
            'name' => $data['name'],
            'company' => $data['company'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address_id' => $addressId,
            'notes' => $data['notes'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'ocr_raw' => $data['ocr_raw'] ?? null,
            'duplicate_of_id' => $data['duplicate_of_id'] ?? null,
            'search_text' => $data['search_text'] ?? null,
            'source' => $data['source'] ?? 'manual',
        ]);

        UserNotification::log($request->user()->id, [
            'type'        => 'contact.created',
            'title'       => 'New contact created',
            'body'        => $contact->name . ($contact->company ? ' · ' . $contact->company : ''),
            'data'        => ['contact_id' => $contact->id],
            'contact_id'  => $contact->id,
        ]);

        return response()->json(['data' => $contact->load(['tags', 'address.city', 'address.state', 'address.country'])], 201);
    }

    /**
     * @OA\Get(
     *   path="/api/contacts/{id}",
     *   tags={"Contacts"},
     *   summary="Get contact details",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="Contact found",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="id", type="integer"),
     *       @OA\Property(property="name", type="string"),
     *       @OA\Property(property="email", type="string", nullable=true),
     *       @OA\Property(property="phone", type="string", nullable=true),
     *       @OA\Property(property="company", type="string", nullable=true),
     *       @OA\Property(property="job_title", type="string", nullable=true),
     *       @OA\Property(property="notes", type="string", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $r, Contact $contact)
    {
        $this->authorizeOwner($r, $contact);
        $uid = $r->user()->id;
        $contact->load([
            'tags' => fn($t) => $t->where('tags.owner_user_id', $uid),
            'address.city',
            'address.state',
            'address.country'
        ]);
        return ['data' => $contact];
    }

    /**
     * @OA\Put(
     *     path="/api/contacts/{contact}",
     *     tags={"Contacts"},
     *     summary="Update a contact",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="contact", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="company", type="string", maxLength=255),
     *             @OA\Property(property="job_title", type="string", maxLength=255),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255),
     *             @OA\Property(property="phone", type="string", maxLength=50),
     *             @OA\Property(property="address_detail", type="string", maxLength=255),
     *             @OA\Property(property="notes", type="string"),
     *             @OA\Property(property="linkedin_url", type="string", format="url", maxLength=255),
     *             @OA\Property(property="website_url", type="string", format="url", maxLength=255)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", nullable=true),
     *                 @OA\Property(property="phone", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Contact $contact)
    {
        $this->authorizeOwner($request, $contact);

        $payload = $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'company'         => 'sometimes|nullable|string|max:255',
            'job_title'       => 'sometimes|nullable|string|max:255',
            'email'           => 'sometimes|nullable|email|max:255',
            'phone'           => 'sometimes|nullable|string|max:50',
            'address_detail'  => 'sometimes|nullable|string|max:255',
            'city'            => 'sometimes|nullable|string|max:20',
            'state'           => 'sometimes|nullable|string|max:20',
            'country'         => 'sometimes|nullable|string|max:10',
            'notes'           => 'sometimes|nullable|string',
            'linkedin_url'    => 'sometimes|nullable|url|max:255',
            'website_url'     => 'sometimes|nullable|url|max:255',
            'ocr_raw'         => 'sometimes|nullable|string',
            'duplicate_of_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(
                    fn($q) => $q->where('owner_user_id', $request->user()->id)
                ),
                Rule::notIn([$contact->id]),
            ],
            'search_text'     => 'sometimes|nullable|string',
            'source'          => 'sometimes|nullable|string|max:50',
        ]);

        // Prevent circular duplicate A↔B
        if (!empty($payload['duplicate_of_id'])) {
            $target = Contact::select('id', 'duplicate_of_id')
                ->where('owner_user_id', $request->user()->id)
                ->find($payload['duplicate_of_id']);
            if ($target && (int) $target->duplicate_of_id === (int) $contact->id) {
                return response()->json(['message' => 'Circular duplicate link (A↔B) is not allowed'], 422);
            }
        }

        // Xử lý address
        if ($request->hasAny(['address_detail', 'city', 'state', 'country'])) {
            // Lấy ID từ code
            $cityId = $request->city ? DB::table('cities')->where('code', $request->city)->value('id') : null;
            $stateId = $request->state ? DB::table('states')->where('code', $request->state)->value('id') : null;
            $countryId = $request->country ? DB::table('countries')->where('code', $request->country)->value('id') : null;

            if ($contact->address_id) {
                // Cập nhật address hiện tại
                $existingAddress = Address::find($contact->address_id);
                if ($existingAddress) {
                    $existingAddress->update([
                        'address_detail' => $request->input('address_detail', $existingAddress->address_detail),
                        'city_id' => $cityId ?? $existingAddress->city_id,
                        'state_id' => $stateId ?? $existingAddress->state_id,
                        'country_id' => $countryId ?? $existingAddress->country_id,
                    ]);
                }
            } else {
                // Tạo address mới nếu có ít nhất address_detail hoặc city
                if ($request->filled('address_detail') || $request->filled('city')) {
                    $newAddress = Address::create([
                        'address_detail' => $request->address_detail,
                        'city_id' => $cityId,
                        'state_id' => $stateId,
                        'country_id' => $countryId,
                    ]);
                    $payload['address_id'] = $newAddress->id;
                }
            }
        }

        // Loại bỏ các field address khỏi payload
        unset($payload['address_detail'], $payload['city'], $payload['state'], $payload['country']);

        $contact->update($payload);

        return ['data' => $contact->fresh()->load(['tags', 'address.city', 'address.state', 'address.country'])];
    }

    /**
     * @OA\Delete(
     *     path="/api/contacts/{contact}",
     *     tags={"Contacts"},
     *     summary="Delete a contact",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="contact", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Contact deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Contact not found")
     * )
     */
    public function destroy(Request $r, Contact $contact)
    {
        $this->authorizeOwner($r, $contact);
        $contact->delete();
        return response()->noContent();
    }

    /**
     * @OA\Post(
     *     path="/api/contacts/{contact}/tags",
     *     tags={"Contacts"},
     *     summary="Attach tags to a contact",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="contact", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="names", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tags attached successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="tags", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string")
     *             ))
     *         )
     *     )
     * )
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
     * @OA\Delete(
     *     path="/api/contacts/{contact}/tags/{tag}",
     *     tags={"Contacts"},
     *     summary="Detach a tag from a contact",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="contact", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="tag", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Tag detached successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string")
     *         )
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/contacts/export",
     *     tags={"Contacts"},
     *     summary="Export contacts to Excel/CSV",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="ids", in="query", description="Specific contact IDs to export (comma-separated)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="q", in="query", description="Search term filter", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tag_ids", in="query", description="Filter by tag IDs", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tags", in="query", description="Filter by tag names", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tag_mode", in="query", description="Tag matching mode", @OA\Schema(type="string", enum={"any", "all"})),
     *     @OA\Parameter(name="without_tag", in="query", description="Exclude contacts with this tag", @OA\Schema(type="string")),
     *     @OA\Parameter(name="exclude_ids", in="query", description="Exclude contact IDs", @OA\Schema(type="string")),
     *     @OA\Parameter(name="format", in="query", description="Export format", @OA\Schema(type="string", enum={"xlsx", "csv"}, default="xlsx")),
     *     @OA\Parameter(name="sort", in="query", description="Sort field", @OA\Schema(type="string", enum={"name", "-name", "id", "-id"})),
     *     @OA\Response(
     *         response=200,
     *         description="File download",
     *         @OA\MediaType(mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
     *     )
     * )
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
            $tagNames = array_values(array_filter(array_map(fn($s) => ltrim(trim($s), '#'), $tagNames)));
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
        $file    = 'contacts_' . now()->format('Ymd_His') . '.' . $format;

        return $format === 'csv'
            ? Excel::download($export, $file, \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, $file, \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * @OA\Post(
     *     path="/api/contacts/import",
     *     tags={"Contacts"},
     *     summary="Import contacts from Excel/CSV file",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", format="binary", description="Excel or CSV file"),
     *                 @OA\Property(property="match_by", type="string", enum={"id", "email", "phone"}, description="Field to match existing contacts")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Import completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="summary", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
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

    /**
     * @OA\Post(
     *     path="/api/contacts/bulk-delete",
     *     tags={"Contacts"},
     *     summary="Bulk delete contacts",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), description="Contact IDs to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contacts deleted successfully",
     *         @OA\JsonContent(@OA\Property(property="deleted", type="integer", description="Number of contacts deleted"))
     *     )
     * )
     */
    public function bulkDelete(Request $r)
    {
        $uid = $r->user()->id;
        $data = $r->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => 'integer']);
        $count = Contact::where('owner_user_id', $uid)->whereIn('id', $data['ids'])->delete();
        return response()->json(['deleted' => $count]);
    }

    /**
     * @OA\Get(
     *     path="/api/contacts/export-template",
     *     tags={"Contacts"},
     *     summary="Download import template",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="format", in="query", description="Format", @OA\Schema(type="string", enum={"xlsx", "csv"}, default="xlsx")),
     *     @OA\Response(response=200, description="Template file download")
     * )
     */
    public function exportTemplate(Request $r)
    {
        $export = new ContactsTemplateExport();
        $format = strtolower((string)$r->query('format', 'xlsx'));
        $file = 'contacts_template.' . $format;

        return $format === 'csv'
            ? Excel::download($export, $file, \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, $file, \Maatwebsite\Excel\Excel::XLSX);
    }
}
