<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('shares', function (Blueprint $t) {
      $t->id();
      $t->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
      $t->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
      $t->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
      $t->string('share_link_token')->nullable()->unique(); // e-card link
      $t->string('status')->default('pending')->index();    // pending|accepted|declined|expired
      $t->json('permissions')->nullable();                 
      $t->text('message')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('shares'); }
};
