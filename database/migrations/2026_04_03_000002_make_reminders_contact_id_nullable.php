<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->change();
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable(false)->change();
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
        });
    }
};
