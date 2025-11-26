<?php

namespace App\Http\Controllers;

use App\Models\BusinessCard;
use App\Models\Contact;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BusinessCardController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/business-card",
     *   tags={"Business Card"},
     *   summary="Get user's business card",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="Business card found",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="id", type="integer"),
     *       @OA\Property(property="full_name", type="string"),
     *       @OA\Property(property="job_title", type="string", nullable=true),
     *       @OA\Property(property="email", type="string"),
     *       @OA\Property(property="phone", type="string", nullable=true),
     *       @OA\Property(property="mobile", type="string", nullable=true),
     *       @OA\Property(property="website", type="string", nullable=true),
     *       @OA\Property(property="linkedin", type="string", nullable=true),
     *       @OA\Property(property="facebook", type="string", nullable=true),
     *       @OA\Property(property="twitter", type="string", nullable=true),
     *       @OA\Property(
     *         property="company",
     *         type="object",
     *         nullable=true,
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="website", type="string", nullable=true),
     *         @OA\Property(property="logo", type="string", nullable=true)
     *       ),
     *       @OA\Property(
     *         property="address",
     *         type="object",
     *         nullable=true,
     *         @OA\Property(property="id", type="integer"),
     *         @OA\Property(property="address_detail", type="string", nullable=true),
     *         @OA\Property(property="city_id", type="integer", nullable=true),
     *         @OA\Property(property="state_id", type="integer", nullable=true),
     *         @OA\Property(property="country_id", type="integer", nullable=true)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=204, description="No business card")
     * )
     */
    public function show(Request $request)
    {
        $card = $request->user()
            ->businessCard()
            ->with(['company', 'address.city', 'address.state', 'address.country'])
            ->first();

        // Trả về trực tiếp, không wrap thêm "data"
        return response()->json($card);
    }

    /**
     * @OA\Get(
     *   path="/api/business-card/public/{slug}",
     *   tags={"Business Card"},
     *   summary="Get public business card by slug",
     *   @OA\Parameter(
     *     name="slug",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Public card",
     *     @OA\JsonContent(type="object")
     *   ),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function showPublic($slug)
    {
        $card = BusinessCard::where('slug', $slug)
            ->where('is_public', true)
            ->with(['company', 'address.city', 'address.state', 'address.country'])
            ->firstOrFail();

        $card->increment('view_count');

        return response()->json([
            'id'         => $card->id,
            'slug'       => $card->slug,
            'full_name'  => $card->full_name,
            'job_title'  => $card->job_title,
            'email'      => $card->email,
            'phone'      => $card->phone,
            'mobile'     => $card->mobile,
            'website'    => $card->website,
            'linkedin'   => $card->linkedin,
            'facebook'   => $card->facebook,
            'twitter'    => $card->twitter,
            'avatar'     => $card->avatar,
            'company'    => $card->company ? [
                'name'    => $card->company->name,
                'website' => $card->company->website,
                'logo'    => $card->company->logo,
            ] : null,
            'address'    => $card->address?->only(['id', 'address_detail', 'city_id', 'state_id', 'country_id']),
            'view_count' => $card->view_count,
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/business-card/connect/{slug}",
     *   tags={"Business Card"},
     *   summary="Connect with business card owner",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="slug",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(response=201, description="Connected and contact created"),
     *   @OA\Response(response=200, description="Already connected"),
     *   @OA\Response(response=400, description="Cannot connect with yourself"),
     *   @OA\Response(response=404, description="Public card not found")
     * )
     */
    public function connect(Request $request, $slug)
    {
        $card = BusinessCard::where('slug', $slug)
            ->where('is_public', true)
            ->with(['company', 'address'])
            ->firstOrFail();

        $currentUser = $request->user();

        if ($card->user_id === $currentUser->id) {
            return response()->json(['message' => 'Cannot connect with yourself'], 400);
        }

        $existingContact = Contact::where('owner_user_id', $currentUser->id)
            ->when($card->email, fn($q) => $q->where('email', $card->email))
            ->first();

        if ($existingContact) {
            return response()->json([
                'message' => 'Already connected',
                'contact' => $existingContact->load(['tags', 'address.city', 'address.state', 'address.country']),
            ], 200);
        }

        // ====== Address ======
        $addressId = null;

        if ($card->address_id) {
            $addressId = $card->address_id;
        } else {
            $addressDetail = $card->address ?? null;
            $cityCode      = $card->city ?? null;
            $stateCode     = $card->state ?? null;
            $countryCode   = $card->country ?? null;

            if ($addressDetail || $cityCode || $stateCode || $countryCode) {
                $cityId    = $cityCode    ? DB::table('cities')->where('code', $cityCode)->value('id')       : null;
                $stateId   = $stateCode   ? DB::table('states')->where('code', $stateCode)->value('id')      : null;
                $countryId = $countryCode ? DB::table('countries')->where('code', $countryCode)->value('id') : null;

                $addr = Address::create([
                    'address_detail' => $addressDetail,
                    'city_id'        => $cityId,
                    'state_id'       => $stateId,
                    'country_id'     => $countryId,
                ]);
                $addressId = $addr->id;
            }
        }

        // ====== Contact ======
        $contact = Contact::create([
            'owner_user_id'   => $currentUser->id,
            'name'            => $card->full_name,
            'email'           => $card->email,
            'phone'           => $card->phone ?: $card->mobile,
            'company'         => $card->company?->name,
            'job_title'       => $card->job_title,
            'address_id'      => $addressId,
            'linkedin_url'    => $card->linkedin,
            'website_url'     => $card->website,
            'notes'           => null,
            'source'          => 'business_card',
        ]);

        return response()->json([
            'message' => 'Connected successfully',
            'contact' => $contact->load(['tags', 'address.city', 'address.state', 'address.country']),
        ], 201);
    }

    /**
     * @OA\Post(
     *   path="/api/business-card",
     *   tags={"Business Card"},
     *   summary="Create or update user's business card",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"full_name","email"},
     *       @OA\Property(property="company_id", type="integer", nullable=true),
     *       @OA\Property(property="full_name", type="string"),
     *       @OA\Property(property="job_title", type="string", nullable=true),
     *       @OA\Property(property="department", type="string", nullable=true),
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="phone", type="string", nullable=true),
     *       @OA\Property(property="mobile", type="string", nullable=true),
     *       @OA\Property(property="website", type="string", nullable=true),
     *       @OA\Property(property="address_detail", type="string", nullable=true),
     *       @OA\Property(property="city", type="string", nullable=true),
     *       @OA\Property(property="state", type="string", nullable=true),
     *       @OA\Property(property="country", type="string", nullable=true),
     *       @OA\Property(property="linkedin", type="string", nullable=true),
     *       @OA\Property(property="facebook", type="string", nullable=true),
     *       @OA\Property(property="twitter", type="string", nullable=true),
     *       @OA\Property(property="is_public", type="boolean", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=200, description="Saved"),
     *   @OA\Response(response=422, description="Validation or save failed")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id'     => 'nullable|exists:companies,id',
            'full_name'      => 'required|string|max:255',
            'job_title'      => 'nullable|string|max:255',
            'department'     => 'nullable|string|max:255',
            'email'          => 'required|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'mobile'         => 'nullable|string|max:50',
            'website'        => 'nullable|url|max:255',
            'address_detail' => 'nullable|string|max:255',
            'city'           => 'nullable|string|max:20|exists:cities,code',
            'state'          => 'nullable|string|max:20|exists:states,code',
            'country'        => 'nullable|string|max:10|exists:countries,code',
            'linkedin'       => 'nullable|url|max:255',
            'facebook'       => 'nullable|url|max:255',
            'twitter'        => 'nullable|url|max:255',
            'avatar'         => 'nullable|image|max:2048',
            'notes'          => 'nullable|string',
            'is_public'      => 'nullable|boolean',
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }
        $data['user_id'] = $request->user()->id;

        DB::beginTransaction();
        try {
            $card = $request->user()->businessCard;

            $cityId    = $request->filled('city')    ? DB::table('cities')->where('code', $request->input('city'))->value('id') : null;
            $stateId   = $request->filled('state')   ? DB::table('states')->where('code', $request->input('state'))->value('id') : null;
            $countryId = $request->filled('country') ? DB::table('countries')->where('code', $request->input('country'))->value('id') : null;

            $hasAddress = $request->filled('address_detail') || $cityId || $stateId || $countryId;

            if ($hasAddress) {
                if ($card && $card->address_id) {
                    $addr = Address::find($card->address_id);
                    if ($addr) {
                        $addr->update([
                            'address_detail' => $request->input('address_detail', $addr->address_detail),
                            'city_id'        => $cityId    ?? $addr->city_id,
                            'state_id'       => $stateId   ?? $addr->state_id,
                            'country_id'     => $countryId ?? $addr->country_id,
                        ]);
                        $data['address_id'] = $addr->id;
                    }
                } else {
                    $addr = Address::create([
                        'address_detail' => $request->input('address_detail'),
                        'city_id'        => $cityId,
                        'state_id'       => $stateId,
                        'country_id'     => $countryId,
                    ]);
                    $data['address_id'] = $addr->id;
                }
            }

            if ($card) {
                if ($request->hasFile('avatar') && $card->avatar) {
                    Storage::disk('public')->delete($card->avatar);
                }
                $card->update($data);
            } else {
                $card = BusinessCard::create($data);
            }

            DB::commit();
            return response()->json(
                $card->load(['company', 'address.city', 'address.state', 'address.country'])
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Save failed', 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @OA\Delete(
     *   path="/api/business-card",
     *   tags={"Business Card"},
     *   summary="Delete user's business card",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Deleted"),
     *   @OA\Response(response=404, description="No business card found")
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
