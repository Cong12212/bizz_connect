<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {

    Schema::create('notes', function (Blueprint $t) {
      $t->id();
      $t->morphs('noteable'); // noteable_type, noteable_id
      $t->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
      $t->text('content');
      $t->timestamps();
      $t->softDeletes();
        });

    Schema::create('activities', function (Blueprint $t) {
      $t->id();
      $t->morphs('subject'); // subject_type, subject_id
      $t->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
      $t->string('type'); // created|updated|merged|shared|called|emailed|met|...
      $t->json('meta')->nullable();
      $t->dateTime('occurred_at')->index();
      $t->timestamps();
      $t->index(['subject_type','subject_id','occurred_at']);
    });

    Schema::create('files', function (Blueprint $t) {
      $t->id();
      $t->morphs('fileable'); // fileable_type, fileable_id (vd: Contact)
      $t->string('disk')->default('public');
      $t->string('path');
      $t->string('mime')->nullable();
      $t->unsignedBigInteger('size')->nullable();
      $t->string('original_name')->nullable();
      $t->timestamps();
        });
  }

  public function down(): void {
    Schema::dropIfExists('files');
    Schema::dropIfExists('activities');
    Schema::dropIfExists('notes');
  }
};
