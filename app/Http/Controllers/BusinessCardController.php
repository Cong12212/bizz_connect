<?php

namespace App\Http\Controllers;

use App\Models\BusinessCard;
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
     * @OA\Post(
     *     path="/api/business-card",
     *     tags={"Business Card"},
     *     summary="Create or update user's business card",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true),
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
            'linkedin' => 'nullable|url|max:255',
            'facebook' => 'nullable|url|max:255',
            'twitter' => 'nullable|url|max:255',
            'avatar' => 'nullable|image|max:2048',
            'notes' => 'nullable|string',
        ]);

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
