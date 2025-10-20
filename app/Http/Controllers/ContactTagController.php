<?php
// app/Http/Controllers/ContactTagController.php
namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\Request;

class ContactTagController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/contacts/{contact}/tags/attach",
     *     tags={"Contact Tags"},
     *     summary="Attach tags to a contact",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="contact",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 description="Existing tag IDs to attach"
     *             ),
     *             @OA\Property(
     *                 property="names",
     *                 type="array",
     *                 @OA\Items(type="string", maxLength=100),
     *                 description="Tag names to create and attach"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tags attached successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Contact")
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not contact owner"),
     *     @OA\Response(response=404, description="Contact not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

        // Create tags from names (personal scope)
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

    /**
     * @OA\Delete(
     *     path="/api/contacts/{contact}/tags/{tag}/detach",
     *     tags={"Contact Tags"},
     *     summary="Detach a tag from a contact",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="contact",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Contact ID"
     *     ),
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Tag ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tag detached successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Contact")
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not contact owner"),
     *     @OA\Response(response=404, description="Contact or tag not found")
     * )
     */
    public function detach(Request $r, Contact $contact, Tag $tag)
    {
        $this->authorizeOwner($r, $contact);
        $contact->tags()->detach($tag->id);
        return $contact->load('tags');
    }

    private function authorizeOwner(Request $r, Contact $c)
    {
        // personal ownership only
        abort_unless($c->owner_user_id === $r->user()->id, 403);
    }
}
