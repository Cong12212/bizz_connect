<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function show(Request $request)
    {
        $company = $request->user()->company?->load(['address.city', 'address.state', 'address.country']);
        return $company ? response()->json($company) : response()->json(null, 204);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'tax_code'       => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'website'        => 'nullable|url|max:255',
            'description'    => 'nullable|string',
            'logo'           => 'nullable|image|max:2048',

            // địa chỉ (code)
            'address_detail' => 'nullable|string|max:255',
            'city'           => 'nullable|string|max:20|exists:cities,code',
            'state'          => 'nullable|string|max:20|exists:states,code',
            'country'        => 'nullable|string|max:10|exists:countries,code',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        DB::beginTransaction();
        try {
            $user    = $request->user();
            $company = $user->company; // qua users.company_id

            // Map code -> id và tạo/cập nhật Address
            $cityId    = $request->filled('city')    ? DB::table('cities')->where('code', $request->input('city'))->value('id') : null;
            $stateId   = $request->filled('state')   ? DB::table('states')->where('code', $request->input('state'))->value('id') : null;
            $countryId = $request->filled('country') ? DB::table('countries')->where('code', $request->input('country'))->value('id') : null;
            $hasAddr   = $request->filled('address_detail') || $cityId || $stateId || $countryId;

            if ($company) {
                if ($hasAddr) {
                    if ($company->address_id) {
                        $addr = Address::find($company->address_id);
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

                if ($request->hasFile('logo') && $company->logo) {
                    Storage::disk('public')->delete($company->logo);
                }
                $company->update($data);
            } else {
                if ($hasAddr) {
                    $addr = Address::create([
                        'address_detail' => $request->input('address_detail'),
                        'city_id'        => $cityId,
                        'state_id'       => $stateId,
                        'country_id'     => $countryId,
                    ]);
                    $data['address_id'] = $addr->id;
                }
                $company = Company::create($data);
                // gán cho user
                $user->company()->associate($company);
                $user->save();
            }

            DB::commit();
            return response()->json($company->load(['address.city', 'address.state', 'address.country']));
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Save failed', 'error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, $id)
    {
        // Tùy bạn có muốn giữ endpoint này không. Nếu giữ, đảm bảo
        // user chỉ update công ty của chính họ:
        $company = $request->user()->company;
        abort_unless($company && (int)$company->id === (int)$id, 403);

        // Reuse logic store() để cập nhật
        return $this->store($request);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        if (!$company) {
            return response()->json(['message' => 'No company found'], 404);
        }

        if ($company->logo) {
            Storage::disk('public')->delete($company->logo);
        }

        // Bỏ liên kết trước khi xóa
        $user->company()->dissociate();
        $user->save();

        $company->delete();
        return response()->json(['message' => 'Company deleted']);
    }
}
