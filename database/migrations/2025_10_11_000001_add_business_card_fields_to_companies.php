<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'user_id')) {
                $table->foreignId('user_id')->after('id')->unique()->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('companies', 'industry')) {
                $table->string('industry')->nullable()->after('domain');
            }
            if (!Schema::hasColumn('companies', 'description')) {
                $table->text('description')->nullable()->after('industry');
            }
            if (!Schema::hasColumn('companies', 'website')) {
                $table->string('website')->nullable()->after('description');
            }
            if (!Schema::hasColumn('companies', 'email')) {
                $table->string('email')->nullable()->after('website');
            }
            if (!Schema::hasColumn('companies', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('companies', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('companies', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (!Schema::hasColumn('companies', 'country')) {
                $table->string('country')->nullable()->after('city');
            }
            if (!Schema::hasColumn('companies', 'logo')) {
                $table->string('logo')->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $columns = ['logo', 'country', 'city', 'address', 'phone', 'email', 'website', 'description', 'industry'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('companies', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
