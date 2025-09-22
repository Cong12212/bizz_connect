<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('company_user', function (Blueprint $t) {
      $t->id();
      $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
      $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
      $t->string('role')->default('member'); // owner|admin|member|viewer
      $t->timestamps();
      $t->softDeletes();

      $t->unique(['company_id','user_id']);
      $t->index(['company_id','role']);
    });
  }
  public function down(): void { Schema::dropIfExists('company_user'); }
};
