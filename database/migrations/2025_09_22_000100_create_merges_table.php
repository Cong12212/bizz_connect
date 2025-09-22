<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('merges', function (Blueprint $t) {
      $t->id();
      $t->foreignId('primary_contact_id')->constrained('contacts')->cascadeOnDelete();
      $t->foreignId('duplicate_contact_id')->constrained('contacts')->cascadeOnDelete();
      $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
      $t->timestamp('resolved_at')->nullable();
      $t->string('strategy')->default('keep_primary'); // keep_primary|combine_fields|manual
      $t->json('meta')->nullable();
      $t->timestamps();

      $t->unique(['primary_contact_id','duplicate_contact_id']);
      $t->index(['resolved_at','strategy']);
    });
  }
  public function down(): void { Schema::dropIfExists('merges'); }
};
