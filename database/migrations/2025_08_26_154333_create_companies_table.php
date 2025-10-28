<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('companies', function (Blueprint $t) {
      $t->id();
      $t->string('name');
      $t->string('domain')->nullable()->unique();
      $t->string('plan')->default('free');
      $t->string('status')->default('active');
      $t->timestamps();
      $t->softDeletes();
      $t->index(['plan', 'status']);
    });
  }
  public function down(): void
  {
    Schema::dropIfExists('companies');
  }
};
