<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('audit_logs', function (Blueprint $t) {
      $t->id();
      $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
      $t->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
      $t->string('action'); // created_contact, updated_contact, delete, export, import, etc.
      $t->string('model_type')->nullable();
      $t->unsignedBigInteger('model_id')->nullable();
      $t->json('changes')->nullable(); // before/after
      $t->string('ip')->nullable();
      $t->text('ua')->nullable();
      $t->timestamps();

      $t->index(['company_id','created_at']);
      $t->index(['model_type','model_id']);
    });
  }
  public function down(): void { Schema::dropIfExists('audit_logs'); }
};
