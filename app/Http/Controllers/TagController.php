<?php
// app/Http/Controllers/TagController.php
namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $r)
    {
        $q = Tag::query()->where('owner_user_id', $r->user()->id);

        if ($term = $r->query('q')) {
            $q->where('name', 'like', "%{$term}%");
        }

        return $q->orderBy('name')->paginate(100);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100']);

        $tag = Tag::firstOrCreate([
            'owner_user_id' => $r->user()->id,
            'name' => $data['name'],
        ]);

        return response()->json($tag, 201);
    }

    public function update(Request $r, Tag $tag)
    {
        abort_unless($tag->owner_user_id === $r->user()->id, 403);

        $data = $r->validate(['name' => 'required|string|max:100']);

        // tránh trùng tên trong phạm vi user
        $exists = Tag::where('owner_user_id', $r->user()->id)
            ->where('name', $data['name'])
            ->where('id', '<>', $tag->id)
            ->exists();
        abort_if($exists, 422, 'This tag name already exists.');

        $tag->update(['name' => $data['name']]);
        return $tag;
    }

    public function destroy(Request $r, Tag $tag)
    {
        abort_unless($tag->owner_user_id === $r->user()->id, 403);
        $tag->delete();
        return response()->noContent();
    }
}
