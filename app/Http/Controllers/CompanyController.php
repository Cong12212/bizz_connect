<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/company",
     *     tags={"Company"},
     *     summary="Get user's company",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Company details")
     * )
     */
    public function show(Request $request)
    {
        $company = $request->user()->company;
        return $company ? response()->json($company) : response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/api/company",
     *     tags={"Company"},
     *     summary="Create or update user's company",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Company saved")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $data['user_id'] = $request->user()->id;

        $company = $request->user()->company;
        if ($company) {
            // Update existing
            if ($request->hasFile('logo') && $company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $company->update($data);
        } else {
            // Create new
            $company = Company::create($data);
        }

        return response()->json($company);
    }

    /**
     * @OA\Delete(
     *     path="/api/company",
     *     tags={"Company"},
     *     summary="Delete user's company",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Company deleted")
     * )
     */
    public function destroy(Request $request)
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['message' => 'No company found'], 404);
        }

        if ($company->logo) {
            Storage::disk('public')->delete($company->logo);
        }

        $company->delete();
        return response()->json(['message' => 'Company deleted']);
    }
}
