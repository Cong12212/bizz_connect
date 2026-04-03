<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('contacts', 'address_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->foreignId('address_id')
                    ->nullable()
                    ->after('phone')
                    ->constrained('addresses')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contacts', 'address_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropForeign(['address_id']);
                $table->dropColumn('address_id');
            });
        }
    }
};
