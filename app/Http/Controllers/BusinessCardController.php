<?php

namespace App\Http\Controllers;

use App\Models\BusinessCard;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BusinessCardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/business-card",
     *     tags={"Business Card"},
     *     summary="Get user's business card",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Business card details")
     * )
     */
    public function show(Request $request)
    {
        $card = $request->user()->businessCard()->with('company')->first();
        return $card ? response()->json($card) : response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/business-card/public/{slug}",
     *     tags={"Business Card"},
     *     summary="Get public business card by slug",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Public business card")
     * )
     */
    public function showPublic($slug)
    {
        $card = BusinessCard::where('slug', $slug)
            ->where('is_public', true)
            ->with('company')
            ->firstOrFail();

        // Increment view count
        $card->increment('view_count');

        return response()->json([
            'id' => $card->id,
            'slug' => $card->slug,
            'full_name' => $card->full_name,
            'job_title' => $card->job_title,
            'email' => $card->email,
            'phone' => $card->phone,
            'mobile' => $card->mobile,
            'website' => $card->website,
            'linkedin' => $card->linkedin,
            'facebook' => $card->facebook,
            'twitter' => $card->twitter,
            'avatar' => $card->avatar,
            'company' => $card->company ? [
                'name' => $card->company->name,
                'industry' => $card->company->industry,
                'website' => $card->company->website,
                'logo' => $card->company->logo,
            ] : null,
            'view_count' => $card->view_count,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/business-card/connect/{slug}",
     *     tags={"Business Card"},
     *     summary="Connect with business card owner",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Connection created")
     * )
     */
    public function connect(Request $request, $slug)
    {
        $card = BusinessCard::where('slug', $slug)
            ->where('is_public', true)
            ->with('company')
            ->firstOrFail();

        $currentUser = $request->user();

        // Don't allow connecting with yourself
        if ($card->user_id === $currentUser->id) {
            return response()->json(['message' => 'Cannot connect with yourself'], 400);
        }

        // Check if contact already exists
        $existingContact = Contact::where('user_id', $currentUser->id)
            ->where('email', $card->email)
            ->first();

        if ($existingContact) {
            return response()->json(['message' => 'Already connected', 'contact' => $existingContact], 200);
        }

        // Create new contact - map business card fields to contact fields
        $contact = Contact::create([
            'user_id' => $currentUser->id,
            'name' => $card->full_name,                    // full_name -> name
            'email' => $card->email,                       // email -> email
            'phone' => $card->phone ?? $card->mobile,      // phone hoặc mobile -> phone
            'company' => $card->company?->name,            // company name -> company
            'job_title' => $card->job_title,               // job_title -> job_title
            'address' => $card->address,                   // address -> address
            'linkedin_url' => $card->linkedin,             // linkedin -> linkedin_url
            'website_url' => $card->website,               // website -> website_url
            'notes' => null,                               // notes để trống
            'source' => 'business_card',                   // đánh dấu nguồn
        ]);

        return response()->json([
            'message' => 'Connected successfully',
            'contact' => $contact,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/business-card",
     *     tags={"Business Card"},
     *     summary="Create or update user's business card",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Business card saved")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'full_name' => 'required|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'linkedin' => 'nullable|url|max:255',
            'facebook' => 'nullable|url|max:255',
            'twitter' => 'nullable|url|max:255',
            'avatar' => 'nullable|image|max:2048',
            'notes' => 'nullable|string',
            'is_public' => 'nullable|boolean|in:true,false,1,0',
        ]);

        // Convert to boolean
        if (isset($data['is_public'])) {
            $data['is_public'] = filter_var($data['is_public'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $data['user_id'] = $request->user()->id;

        $card = $request->user()->businessCard;
        if ($card) {
            // Update existing
            if ($request->hasFile('avatar') && $card->avatar) {
                Storage::disk('public')->delete($card->avatar);
            }
            $card->update($data);
        } else {
            // Create new
            $card = BusinessCard::create($data);
        }

        return response()->json($card->load('company'));
    }

    /**
     * @OA\Delete(
     *     path="/api/business-card",
     *     tags={"Business Card"},
     *     summary="Delete user's business card",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroy(Request $request)
    {
        $card = $request->user()->businessCard;
        if (!$card) {
            return response()->json(['message' => 'No business card found'], 404);
        }

        if ($card->avatar) {
            Storage::disk('public')->delete($card->avatar);
        }

        $card->delete();
        return response()->json(['message' => 'Business card deleted']);
    }
}
