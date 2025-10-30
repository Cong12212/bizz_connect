<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate data từ cột 'address' sang bảng addresses
        $contacts = DB::table('contacts')
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->get();

        foreach ($contacts as $contact) {
            // Tạo address mới từ text address cũ
            $addressId = DB::table('addresses')->insertGetId([
                'line1' => $contact->address, // Lưu toàn bộ text vào line1
                'line2' => null,
                'city' => null,
                'state' => null,
                'country' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update contact với address_id mới
            DB::table('contacts')
                ->where('id', $contact->id)
                ->update(['address_id' => $addressId]);
        }
    }

    public function down(): void
    {
        // Rollback: copy address line1 trở lại cột address
        $contacts = DB::table('contacts')
            ->whereNotNull('address_id')
            ->get();

        foreach ($contacts as $contact) {
            $address = DB::table('addresses')->find($contact->address_id);
            if ($address) {
                DB::table('contacts')
                    ->where('id', $contact->id)
                    ->update(['address' => $address->line1]);
            }
        }
    }
};
