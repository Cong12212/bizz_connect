<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('users', function (Blueprint $t) {
      $t->id();
      $t->string('name');
      $t->string('email')->unique();
      $t->timestamp('email_verified_at')->nullable();
      $t->string('password');
      $t->string('phone')->nullable();
      $t->string('avatar_url')->nullable();
      $t->string('locale')->nullable();
      $t->string('timezone')->nullable();
      $t->rememberToken();
      $t->timestamps();
      $t->softDeletes();
      $t->index('email');
    });
  }
  public function down(): void { Schema::dropIfExists('users'); }
};
