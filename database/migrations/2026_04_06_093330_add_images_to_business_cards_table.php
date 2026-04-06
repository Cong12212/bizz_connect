<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_cards', function (Blueprint $table) {
            $table->string('card_image_front')->nullable()->after('avatar');
            $table->string('card_image_back')->nullable()->after('card_image_front');
            $table->string('background_image')->nullable()->after('card_image_back');
        });
    }

    public function down(): void
    {
        Schema::table('business_cards', function (Blueprint $table) {
            $table->dropColumn(['card_image_front', 'card_image_back', 'background_image']);
        });
    }
};
