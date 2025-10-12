<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    // POST /api/billing/subscribe/personal
    public function subscribePersonal(Request $req)
    {
        $data = $req->validate([
            'plan' => 'required|in:pro,pro_plus',
            'period_end' => 'nullable|date',
        ]);

        $user = $req->user();

        return DB::transaction(function() use ($user, $data) {
            // hủy sub cá nhân đang active (nếu có)
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

    // POST /api/billing/subscribe/company
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
