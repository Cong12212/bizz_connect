<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\Request;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;

class ContactController extends Controller
{
   public function index(Request $r)
{
    $per = min(100, (int) $r->query('per_page', 20));

    $q = Contact::query()->where('owner_user_id', $r->user()->id);

    if ($term = trim((string) $r->query('q', ''))) {
        $q->where(function ($w) use ($term) {
            $w->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('company', 'like', "%{$term}%");
        });
    }

    if ($tagIds = $r->query('tag_ids')) {
        $ids = is_array($tagIds) ? $tagIds : explode(',', (string) $tagIds);
        $q->whereHas('tags', fn($t) => $t->whereIn('tags.id', $ids));
    }

    $sort = (string) $r->query('sort', '-id');
    $sort === 'name'   ? $q->orderBy('name')
  : ($sort === '-name' ? $q->orderBy('name', 'desc')
  : ($sort === 'id'    ? $q->orderBy('id')
                       : $q->orderBy('id', 'desc')));

    return $q->with('tags')->paginate($per);
}

public function store(Request $r)
{
    $data = $r->validate([
        'name'    => 'required|string|max:255',
        'company' => 'nullable|string|max:255',
        'email'   => 'nullable|email|max:255',
        'phone'   => 'nullable|string|max:50',
        'address' => 'nullable|string|max:255',
        'notes'   => 'nullable|string',
    ]);

    $data['owner_user_id'] = $r->user()->id; 
    unset($data['company_id']);        
    $c = Contact::create($data);

    return response()->json(['data' => $c->load('tags')], 201);
}

public function update(Request $r, Contact $contact)
{
    $this->authorizeOwner($r, $contact);

    $data = $r->validate([
        'name'    => 'sometimes|required|string|max:255',
        'company' => 'nullable|string|max:255',
        'email'   => 'nullable|email|max:255',
        'phone'   => 'nullable|string|max:50',
        'address' => 'nullable|string|max:255',
        'notes'   => 'nullable|string',
    ]);

    $contact->update($data);
    return ['data' => $contact->fresh()->load('tags')];
}

private function authorizeOwner(Request $r, Contact $c)
{
    abort_unless($c->owner_user_id === $r->user()->id, 403);
}

}
