<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/billing/subscribe/personal",
     *     tags={"Billing"},
     *     summary="Subscribe to personal plan",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plan"},
     *             @OA\Property(property="plan", type="string", enum={"pro", "pro_plus"}, example="pro"),
     *             @OA\Property(property="period_end", type="string", format="date", example="2024-12-31")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="subscription", type="object"),
     *             @OA\Property(property="is_plus", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function subscribePersonal(Request $req)
    {
        $data = $req->validate([
            'plan' => 'required|in:pro,pro_plus',
            'period_end' => 'nullable|date',
        ]);

        $user = $req->user();

        return DB::transaction(function() use ($user, $data) {
            Subscription::where('user_id', $user->id)
                ->active()
                ->update(['status' => 'canceled', 'current_period_end' => now()]);

            $sub = Subscription::create([
                'user_id' => $user->id,
                'plan'    => $data['plan'],
                'status'  => 'active',
                'current_period_start' => now(),
                'current_period_end'   => $data['period_end'] ?? now()->addMonth(),
            ]);

            return response()->json(['subscription' => $sub, 'is_plus' => $user->fresh()->is_plus]);
        });
    }

    /**
     * @OA\Post(
     *     path="/api/billing/subscribe/company",
     *     tags={"Billing"},
     *     summary="Subscribe to company plan",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"company_id", "plan"},
     *             @OA\Property(property="company_id", type="integer", example=1),
     *             @OA\Property(property="plan", type="string", enum={"pro", "pro_plus"}, example="pro_plus"),
     *             @OA\Property(property="period_end", type="string", format="date", example="2024-12-31")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Company subscription created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="subscription", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Company not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function subscribeCompany(Request $req)
    {
        $data = $req->validate([
            'company_id' => 'required|exists:companies,id',
            'plan'       => 'required|in:pro,pro_plus',
            'period_end' => 'nullable|date',
        ]);

        $company = Company::findOrFail($data['company_id']);

        return DB::transaction(function() use ($company, $data) {
            Subscription::where('company_id', $company->id)
                ->active()
                ->update(['status' => 'canceled', 'current_period_end' => now()]);

            $sub = Subscription::create([
                'company_id' => $company->id,
                'plan'       => $data['plan'],
                'status'     => 'active',
                'current_period_start' => now(),
                'current_period_end'   => $data['period_end'] ?? now()->addMonth(),
            ]);

            return response()->json(['subscription' => $sub]);
        });
    }
}
