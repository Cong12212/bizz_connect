<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('audit_logs', function (Blueprint $t) {
      $t->id();
      $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
      $t->string('action');
      $t->string('model_type')->nullable();
      $t->unsignedBigInteger('model_id')->nullable();
      $t->json('changes')->nullable();
      $t->string('ip')->nullable();
      $t->text('ua')->nullable();
      $t->timestamps();

      $t->index(['user_id', 'created_at']);
      $t->index(['model_type', 'model_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('audit_logs');
  }
};
