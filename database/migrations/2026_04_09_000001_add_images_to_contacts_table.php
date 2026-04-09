<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('source');
            $table->string('card_image_front')->nullable()->after('avatar');
            $table->string('card_image_back')->nullable()->after('card_image_front');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'card_image_front', 'card_image_back']);
        });
    }
};
