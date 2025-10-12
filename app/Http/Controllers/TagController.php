<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    // GET /tags?q=&per_page=&sort=name|-name|count|-count
    public function index(Request $r)
    {
        $per = min(100, (int) $r->query('per_page', 100));
        $q = Tag::query()
            ->where('owner_user_id', $r->user()->id)
            ->withCount('contacts'); // <— để FE có contacts_count

        if ($term = trim((string) $r->query('q', ''))) {
            $term = ltrim($term, '#');                 // bỏ dấu # nếu gõ #vip
            $q->where('name', 'like', "%{$term}%");
        }

        // sort theo name hoặc số lượng contacts
        $sort = (string) $r->query('sort', 'name');    // name|-name|count|-count
        $sort === 'name'   ? $q->orderBy('name')
      : ($sort === '-name' ? $q->orderBy('name', 'desc')
      : ($sort === 'count' ? $q->orderBy('contacts_count', 'asc')
      : ($sort === '-count'? $q->orderBy('contacts_count', 'desc')
                           : $q->orderBy('name'))));

        return $q->paginate($per);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100']);
        $name = $this->normalize($data['name']);

        // chặn trùng trong phạm vi user
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

    public function destroy(Request $r, Tag $tag)
    {
        abort_unless($tag->owner_user_id === $r->user()->id, 403);
        $tag->delete();
        return response()->noContent();
    }

    private function normalize(string $name): string
    {
        // bỏ # đầu, trim khoảng trắng, rút gọn space giữa các từ
        $name = trim(ltrim($name, '#'));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        return $name;
    }
}
