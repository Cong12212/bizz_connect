<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
 Schema::create('integrations', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
    $t->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();

    // CHỈNH ĐỘ DÀI
    $t->string('type', 32);      // email|calendar ...
    $t->string('provider', 32);  // gmail|o365|outlook|...
    $t->text('access_token')->nullable();
    $t->text('refresh_token')->nullable();
    $t->timestamp('expires_at')->nullable();
    $t->json('scopes')->nullable();
    $t->string('status', 32)->default('active');

    $t->timestamps();

    // Giữ index gộp sau khi thu ngắn cột
    $t->index(['user_id','company_id','type','provider','status']);
});

  }
  public function down(): void { Schema::dropIfExists('integrations'); }
};
