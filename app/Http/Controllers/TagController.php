<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tags",
     *     tags={"Tags"},
     *     summary="Get tags list with filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term (supports # prefix)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (max 100)",
     *         @OA\Schema(type="integer", default=100, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort field and direction",
     *         @OA\Schema(type="string", enum={"name", "-name", "count", "-count"}, default="name")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated tags list with contact count",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Tag")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $r)
    {
        $per = min(100, (int) $r->query('per_page', 100));
        $q = Tag::query()
            ->where('owner_user_id', $r->user()->id)
            ->withCount('contacts'); // so FE has contacts_count

        if ($term = trim((string) $r->query('q', ''))) {
            $term = ltrim($term, '#');                 // remove # if typed #vip
            $q->where('name', 'like', "%{$term}%");
        }

        // sort by name or number of contacts
        $sort = (string) $r->query('sort', 'name');    // name|-name|count|-count
        $sort === 'name'   ? $q->orderBy('name')
      : ($sort === '-name' ? $q->orderBy('name', 'desc')
      : ($sort === 'count' ? $q->orderBy('contacts_count', 'asc')
      : ($sort === '-count'? $q->orderBy('contacts_count', 'desc')
                           : $q->orderBy('name'))));

        return $q->paginate($per);
    }

    /**
     * @OA\Post(
     *     path="/api/tags",
     *     tags={"Tags"},
     *     summary="Create a new tag",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=100, example="#VIP")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tag created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Tag")
     *     ),
     *     @OA\Response(response=422, description="Validation error or duplicate tag name")
     * )
     */
    public function store(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100']);
        $name = $this->normalize($data['name']);

        // prevent duplicate within user scope
        $exists = Tag::where('owner_user_id', $r->user()->id)->where('name', $name)->exists();
        if ($exists) {
            return response()->json(['message' => 'This tag name already exists.'], 422);
        }

        $tag = Tag::create([
            'owner_user_id' => $r->user()->id,
            'name'          => $name,
        ]);

        return response()->json($tag->loadCount('contacts'), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/tags/{tag}",
     *     tags={"Tags"},
     *     summary="Update a tag",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=100, example="Important")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tag updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Tag")
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not tag owner"),
     *     @OA\Response(response=422, description="Validation error or duplicate tag name")
     * )
     */
    public function update(Request $r, Tag $tag)
    {
        abort_unless($tag->owner_user_id === $r->user()->id, 403);

        $data = $r->validate(['name' => 'required|string|max:100']);
        $name = $this->normalize($data['name']);

        $exists = Tag::where('owner_user_id', $r->user()->id)
            ->where('name', $name)
            ->where('id', '<>', $tag->id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'This tag name already exists.'], 422);
        }

        $tag->update(['name' => $name]);
        return $tag->loadCount('contacts');
    }

    /**
     * @OA\Delete(
     *     path="/api/tags/{tag}",
     *     tags={"Tags"},
     *     summary="Delete a tag",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Tag deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden - Not tag owner"),
     *     @OA\Response(response=404, description="Tag not found")
     * )
     */
    public function destroy(Request $r, Tag $tag)
    {
        abort_unless($tag->owner_user_id === $r->user()->id, 403);
        $tag->delete();
        return response()->noContent();
    }

    private function normalize(string $name): string
    {
        // remove leading #, trim whitespace, reduce spaces between words
        $name = trim(ltrim($name, '#'));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        return $name;
    }
}
