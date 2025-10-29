<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update companies table
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'address_line1')) {
                $table->string('address_line1')->nullable()->after('address');
                $table->string('address_line2')->nullable()->after('address_line1');
                $table->string('state')->nullable()->after('city');
                $table->string('postal_code')->nullable()->after('state');
            }
        });

        // Update business_cards table
        Schema::table('business_cards', function (Blueprint $table) {
            if (!Schema::hasColumn('business_cards', 'address_line1')) {
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
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['address_line1', 'address_line2', 'state', 'postal_code']);
        });

        Schema::table('business_cards', function (Blueprint $table) {
            $table->dropColumn(['address_line1', 'address_line2', 'city', 'state', 'country', 'postal_code']);
        });
    }
};
