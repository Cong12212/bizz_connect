<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();

            $t->string('type');
            $t->string('title');
            $t->text('body')->nullable();
            $t->json('data')->nullable();

            $t->unsignedBigInteger('contact_id')->nullable();
            $t->unsignedBigInteger('reminder_id')->nullable();

            $t->string('status')->default('unread');
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();

            $t->index(['owner_user_id', 'status']);
            $t->index(['owner_user_id', 'scheduled_at']);
            $t->index(['reminder_id']);
            $t->index(['contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
