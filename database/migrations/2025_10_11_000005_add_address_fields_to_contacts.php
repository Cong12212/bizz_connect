<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'address_line1')) {
                $table->string('address_line1')->nullable()->after('address');
                $table->string('address_line2')->nullable()->after('address_line1');
                $table->string('city')->nullable()->after('address_line2');
                $table->string('state')->nullable()->after('city');
                $table->string('country')->nullable()->after('state');
                $table->string('postal_code')->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['address_line1', 'address_line2', 'city', 'state', 'country', 'postal_code']);
        });
    }
};
