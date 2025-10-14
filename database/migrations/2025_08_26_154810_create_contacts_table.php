<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $t)
        {
            $t->id();

            // Owner (link to user)
            $t->foreignId('owner_user_id')
              ->constrained('users')
              ->cascadeOnDelete();

            // Main information
            $t->string('name');
            $t->string('job_title')->nullable();
            $t->string('company')->nullable();      // <-- only once

            $t->string('email')->nullable();
            $t->string('phone')->nullable();

            // Match with FE
            $t->string('address')->nullable();
            $t->text('notes')->nullable();

            // Meta (optional)
            $t->string('linkedin_url')->nullable();
            $t->string('website_url')->nullable();
            $t->text('ocr_raw')->nullable();
            $t->unsignedBigInteger('duplicate_of_id')->nullable();
            $t->text('search_text')->nullable();
            $t->string('source')->default('manual');

            $t->timestamps();
            $t->softDeletes();

            // Indexes
            $t->index('owner_user_id');
            $t->index('email');
            $t->index('phone');
            $t->fullText(['name', 'company', 'email', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
