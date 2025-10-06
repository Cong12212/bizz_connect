<?php
// app/Http/Controllers/ContactTagController.php
namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\Request;

class ContactTagController extends Controller
{
    public function attach(Request $r, Contact $contact)
    {
        $this->authorizeOwner($r, $contact);

        $data = $r->validate([
            'ids'      => 'array',
            'ids.*'    => 'integer|exists:tags,id',
            'names'    => 'array',
            'names.*'  => 'string|max:100',
        ]);

        $ids = $data['ids'] ?? [];

        // Tạo tag từ tên (scope cá nhân)
        foreach (($data['names'] ?? []) as $name) {
            $tag = Tag::firstOrCreate([
                'owner_user_id' => $r->user()->id,
                'name'          => $name,
            ]);
            $ids[] = $tag->id;
        }

        $contact->tags()->syncWithoutDetaching(array_values(array_unique($ids)));

        return $contact->load('tags');
    }

    public function detach(Request $r, Contact $contact, Tag $tag)
    {
        $this->authorizeOwner($r, $contact);
        $contact->tags()->detach($tag->id);
        return $contact->load('tags');
    }

    private function authorizeOwner(Request $r, Contact $c)
    {
        // chỉ sở hữu cá nhân
        abort_unless($c->owner_user_id === $r->user()->id, 403);
    }
}
