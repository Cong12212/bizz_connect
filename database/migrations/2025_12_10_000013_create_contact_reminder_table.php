<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contact_reminder', function (Blueprint $t) {
            $t->unsignedBigInteger('contact_id');
            $t->unsignedBigInteger('reminder_id');
            $t->timestamps();

            // Composite primary key
            $t->primary(['contact_id', 'reminder_id'], 'contact_reminder_pk');

            // Indexes
            $t->index('contact_id',  'contact_reminder_contact_idx');
            $t->index('reminder_id', 'contact_reminder_reminder_idx');

            // Foreign keys
            $t->foreign('contact_id')
                ->references('id')->on('contacts')
                ->cascadeOnDelete();

            $t->foreign('reminder_id')
                ->references('id')->on('reminders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_reminder');
    }
};
