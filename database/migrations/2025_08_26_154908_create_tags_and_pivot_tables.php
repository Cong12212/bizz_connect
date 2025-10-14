<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('tags', function (Blueprint $t) {
      $t->id();
      $t->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
      $t->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
      $t->string('name');
      $t->timestamps();

      // unique by ownership scope
      $t->unique(['company_id','owner_user_id','name']);
    });

    Schema::create('contact_tag', function (Blueprint $t) {
      $t->id();
      $t->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
      $t->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
      $t->timestamps();

      $t->unique(['contact_id','tag_id']);
      $t->index(['tag_id','contact_id']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('contact_tag');
    Schema::dropIfExists('tags');
  }
};
